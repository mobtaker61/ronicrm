<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TikTokConnection;
use App\Services\TikTokOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TikTokConnectionController extends Controller
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
        $service = app(TikTokOAuthService::class);
        if (! $service->isConfigured()) {
            return redirect()->route('settings.index')->with('error', 'TikTok app is not configured. Set TIKTOK_CLIENT_KEY and TIKTOK_CLIENT_SECRET in .env.');
        }
        $state = Str::random(40);
        $redirectUri = trim((string) config('services.tiktok.redirect_uri', ''));
        if ($redirectUri === '') {
            $redirectUri = route('settings.tiktok.callback');
        }
        $request->session()->put('tiktok_oauth_state', $state);
        $request->session()->put('tiktok_oauth_redirect_uri', $redirectUri);
        $url = $service->getAuthorizationUrl($state, $redirectUri);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('tiktok_oauth_state');
        if (! $state || $request->input('state') !== $state) {
            return redirect()->route('settings.index')->with('error', 'Invalid state. Please try connecting again.');
        }
        if ($request->has('error')) {
            return redirect()->route('settings.index')->with('error', 'TikTok authorization was denied or failed.');
        }
        $code = $request->input('code');
        if (empty($code) || ! is_string($code)) {
            return redirect()->route('settings.index')->with('error', 'No authorization code received.');
        }
        $service = app(TikTokOAuthService::class);
        $redirectUri = $request->session()->pull('tiktok_oauth_redirect_uri');
        if (! is_string($redirectUri) || trim($redirectUri) === '') {
            $redirectUri = trim((string) config('services.tiktok.redirect_uri', ''));
        }
        if ($redirectUri === '') {
            $redirectUri = route('settings.tiktok.callback');
        }

        TikTokConnection::query()->delete();

        $result = $service->exchangeCodeForConnection($code, Auth::id(), $redirectUri);
        if (isset($result['error'])) {
            return redirect()->route('settings.index')->with('error', 'Connection failed: '.$result['error']);
        }

        return redirect()->route('settings.index')->with('success', 'TikTok account connected successfully.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        TikTokConnection::query()->delete();

        return redirect()->route('settings.index')->with('success', 'TikTok account disconnected.');
    }
}
