<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InstagramConnection;
use App\Models\Setting;
use App\Services\MetaInstagramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InstagramConnectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->canManageOrganizationSettings()) {
                abort(403, 'Unauthorized action.');
            }

            return $next($request);
        });
    }

    public function connect(Request $request): RedirectResponse
    {
        $service = app(MetaInstagramService::class);
        if (!$service->isConfigured()) {
            return redirect()->route('settings.index')->with('error', 'Instagram app is not configured. Set META_APP_ID and META_APP_SECRET in .env.');
        }
        $state = Str::random(40);
        $redirectUri = trim((string) config('services.meta_instagram.redirect_uri', ''));
        if ($redirectUri === '') {
            $redirectUri = route('settings.instagram.callback');
        }
        $request->session()->put('instagram_oauth_state', $state);
        $request->session()->put('instagram_oauth_redirect_uri', $redirectUri);
        $url = $service->getAuthorizationUrl($state, $redirectUri);
        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('instagram_oauth_state');
        if (!$state || $request->input('state') !== $state) {
            return redirect()->route('settings.index')->with('error', 'Invalid state. Please try connecting again.');
        }
        if ($request->has('error')) {
            return redirect()->route('settings.index')->with('error', 'Instagram authorization was denied or failed.');
        }
        $code = $request->input('code');
        if (empty($code)) {
            return redirect()->route('settings.index')->with('error', 'No authorization code received.');
        }
        $service = app(MetaInstagramService::class);
        $redirectUri = $request->session()->pull('instagram_oauth_redirect_uri');
        if (! is_string($redirectUri) || trim($redirectUri) === '') {
            $redirectUri = trim((string) config('services.meta_instagram.redirect_uri', ''));
        }
        if ($redirectUri === '') {
            $redirectUri = route('settings.instagram.callback');
        }
        $result = $service->exchangeCodeForConnection($code, Auth::id(), $redirectUri);
        if (isset($result['error'])) {
            return redirect()->route('settings.index')->with('error', 'Connection failed: ' . $result['error']);
        }
        return redirect()->route('settings.index')->with('success', 'Instagram account connected successfully.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $conn = InstagramConnection::getActive();
        if ($conn) {
            $conn->delete();
        }
        Setting::setForOrganization('instagram', array_merge(Setting::getScoped('instagram', []), ['enabled' => false]));
        return redirect()->route('settings.index')->with('success', 'Instagram account disconnected.');
    }
}
