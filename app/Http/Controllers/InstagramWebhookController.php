<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Messaging Webhook (Meta).
 * Verify token and receive message events once Meta App is configured.
 * Stub: accepts GET (verification) and POST (events), returns 200.
 */
class InstagramWebhookController extends Controller
{
    /**
     * GET: Meta verification (hub.mode, hub.verify_token, hub.challenge).
     * Meta sends hub.mode=subscribe, hub.verify_token=<your_token>, hub.challenge=<random>.
     * If verify_token matches our stored token, we must echo hub.challenge back as plain text.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $settings = \App\Models\Setting::get('instagram', []);
        $verifyToken = (string) ($settings['webhook_verify_token'] ?? '');

        if ($mode === 'subscribe' && $verifyToken !== '' && $token === $verifyToken) {
            return response($challenge ?? '', 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Instagram webhook verify failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
            'has_expected' => $verifyToken !== '',
        ]);

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * POST: Receive webhook events (messages, etc.).
     * Stub: log and return 200 until full integration.
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('Instagram webhook received', ['data' => $data]);

        // TODO: When Meta App is connected, parse entry[].messaging and save to instagram_messages
        // and find/create customer by ig_user_id (store in customer_contacts or social_media).

        return response()->json(['ok' => true]);
    }
}
