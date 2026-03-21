<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleContactsBulkJob;
use App\Models\Customer;
use App\Models\GoogleContactsIntegration;
use App\Services\GoogleContactsOAuthService;
use App\Support\GoogleContactsBulkSyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleContactsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->hasRole('admin')) {
                abort(403, 'Unauthorized action. Only administrators can manage Google Contacts sync.');
            }

            return $next($request);
        });
    }

    public function connect(Request $request): RedirectResponse
    {
        $oauth = app(GoogleContactsOAuthService::class);
        if (! $oauth->isConfigured()) {
            return redirect()
                ->route('settings.index', ['tab' => 'google-contacts'])
                ->with('error', 'Google OAuth is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in .env (redirect must match Google Cloud Console).');
        }

        $state = Str::random(40);
        $request->session()->put('google_contacts_oauth_state', $state);

        return redirect()->away($oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('google_contacts_oauth_state');
        if (! $state || $request->input('state') !== $state) {
            return redirect()
                ->route('settings.index', ['tab' => 'google-contacts'])
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        if ($request->has('error')) {
            return redirect()
                ->route('settings.index', ['tab' => 'google-contacts'])
                ->with('error', 'Google authorization was denied or failed: '.(string) $request->input('error_description', $request->input('error')));
        }

        $code = $request->input('code');
        if (empty($code) || ! is_string($code)) {
            return redirect()
                ->route('settings.index', ['tab' => 'google-contacts'])
                ->with('error', 'No authorization code received.');
        }

        $result = app(GoogleContactsOAuthService::class)->exchangeCodeAndSave($code, Auth::id());

        if (! ($result['success'] ?? false)) {
            return redirect()
                ->route('settings.index', ['tab' => 'google-contacts'])
                ->with('error', $result['error'] ?? 'Connection failed.');
        }

        $email = $result['integration']->account_email ?? '';

        return redirect()
            ->route('settings.index', ['tab' => 'google-contacts'])
            ->with('success', 'Google account connected for Contacts sync'.($email !== '' ? " ({$email})" : '').'.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        GoogleContactsIntegration::query()->delete();

        return redirect()
            ->route('settings.index', ['tab' => 'google-contacts'])
            ->with('success', 'Google Contacts connection removed. Existing CRM links (google_people_resource_name) are kept to avoid duplicates on reconnect.');
    }

    /**
     * شروع همگام‌سازی انبوه در پس‌زمینه (بعد از پاسخ HTTP). وضعیت را با syncProgress بخوانید.
     */
    public function startBulkSync(Request $request): JsonResponse
    {
        if (! GoogleContactsIntegration::getSingleton()) {
            return response()->json(['ok' => false, 'message' => 'Google Contacts is not connected.'], 422);
        }

        $total = (int) Customer::query()->count();

        GoogleContactsBulkSyncState::put([
            'status' => 'queued',
            'total' => $total,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'errors' => [],
        ]);

        SyncGoogleContactsBulkJob::dispatch()->afterResponse();

        return response()->json(['ok' => true, 'total' => $total]);
    }

    public function syncProgress(Request $request): JsonResponse
    {
        return response()->json(
            GoogleContactsBulkSyncState::get() ?? ['status' => 'idle', 'total' => 0, 'processed' => 0, 'success' => 0, 'failed' => 0]
        );
    }
}
