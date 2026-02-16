<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSocialMedia;
use App\Models\InstagramConnection;
use App\Models\InstagramMessage;
use App\Models\InstagramWebhookEvent;
use App\Models\SocialMediaType;
use App\Services\MetaInstagramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Instagram Messaging Webhook (Meta).
 * GET: verification (hub.mode, hub.verify_token, hub.challenge).
 * POST: event notifications (validate signature, parse messaging events).
 */
class InstagramWebhookController extends Controller
{
    public function verify(Request $request): Response|HttpResponse
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $expectedToken = config('services.meta_instagram.verify_token', '');
        if (empty($expectedToken)) {
            $settings = \App\Models\Setting::get('instagram', []);
            $expectedToken = (string) ($settings['webhook_verify_token'] ?? '');
        }

        if ($mode === 'subscribe' && $expectedToken !== '' && $token === $expectedToken) {
            $conn = InstagramConnection::getActive();
            if ($conn) {
                $conn->update(['webhook_verified_at' => now()]);
            }
            return response($challenge ?? '', 200)->header('Content-Type', 'text/plain');
        }

        Log::channel('instagram')->warning('Instagram webhook verify failed', [
            'mode' => $mode,
            'has_match' => $token === $expectedToken,
        ]);
        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        Log::channel('instagram')->info('Instagram webhook POST received');

        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');
        $appSecret = config('services.meta_instagram.client_secret', '');
        if ($appSecret !== '' && !$this->validateSignature($rawBody, $signature, $appSecret)) {
            Log::channel('instagram')->warning('Instagram webhook signature invalid', [
                'hint' => 'Use the same Instagram App Secret (Business login settings) in META_APP_SECRET',
                'has_signature' => $signature !== '',
            ]);
            return response('Forbidden', 403);
        }

        $data = $request->all();
        $object = $data['object'] ?? null;
        if (empty($object) || $object !== 'instagram') {
            Log::channel('instagram')->info('Webhook payload ignored', ['object' => $object]);
            return response('', 200);
        }

        $entries = $data['entry'] ?? [];
        if (empty($entries)) {
            Log::channel('instagram')->info('Webhook payload has no entries', ['keys' => array_keys($data)]);
            return response('', 200);
        }

        foreach ($entries as $entry) {
            $igAccountId = $entry['id'] ?? null;
            $connection = InstagramConnection::where('ig_business_account_id', (string) $igAccountId)->first();
            // Fallback: if we have exactly one connection, use it (Meta may send entry.id in a different format than OAuth user_id)
            if (!$connection && InstagramConnection::count() === 1) {
                $connection = InstagramConnection::first();
                Log::channel('instagram')->info('Using single connection for webhook', [
                    'entry_id' => $igAccountId,
                    'connection_ig_id' => $connection->ig_business_account_id,
                ]);
                // Optionally keep DB in sync for next time
                $connection->update([
                    'ig_business_account_id' => (string) $igAccountId,
                    'last_webhook_event_at' => now(),
                ]);
            } elseif (!$connection) {
                Log::channel('instagram')->warning('No connection for entry id', ['entry_id' => $igAccountId]);
                continue;
            } else {
                $connection->update(['last_webhook_event_at' => now()]);
            }
            $messaging = $entry['messaging'] ?? [];
            foreach ($messaging as $event) {
                $this->processMessagingEvent($connection, $event);
            }
        }

        return response('', 200);
    }

    protected function validateSignature(string $payload, string $signatureHeader, string $appSecret): bool
    {
        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
        return hash_equals($expected, $signatureHeader);
    }

    protected function processMessagingEvent(InstagramConnection $connection, array $event): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        $mid = null;
        $eventType = 'unknown';

        if (isset($event['message'])) {
            $eventType = 'message';
            $mid = $event['message']['mid'] ?? null;
            $text = $event['message']['text'] ?? null;
            $attachments = $event['message']['attachments'] ?? [];
            $this->logWebhookEvent($connection, $eventType, $mid, $senderId, $recipientId, $timestamp, $event);
            if ($senderId && $recipientId === $connection->ig_business_account_id && $text !== null) {
                $this->saveIncomingMessage($connection, $senderId, $mid, $text, $attachments);
            }
            return;
        }
        if (isset($event['message_reaction'])) {
            $eventType = 'message_reaction';
            $this->logWebhookEvent($connection, $eventType, null, $senderId, $recipientId, $timestamp, $event);
            return;
        }
        if (isset($event['read'])) {
            $eventType = 'messaging_seen';
            $this->logWebhookEvent($connection, $eventType, null, $senderId, $recipientId, $timestamp, $event);
            return;
        }
        if (isset($event['message_echo'])) {
            $eventType = 'message_echo';
            $this->logWebhookEvent($connection, $eventType, null, $senderId, $recipientId, $timestamp, $event);
            return;
        }
        if (isset($event['postback'])) {
            $eventType = 'messaging_postbacks';
            $this->logWebhookEvent($connection, $eventType, null, $senderId, $recipientId, $timestamp, $event);
            return;
        }

        $this->logWebhookEvent($connection, $eventType, null, $senderId, $recipientId, $timestamp, $event);
    }

    protected function logWebhookEvent(
        InstagramConnection $connection,
        string $eventType,
        ?string $mid,
        ?string $senderId,
        ?string $recipientId,
        $timestamp,
        array $event
    ): void {
        Log::channel('instagram')->info('Instagram webhook event', [
            'connection_id' => $connection->id,
            'event_type' => $eventType,
            'mid' => $mid,
            'sender_id' => $senderId ? substr($senderId, 0, 4) . '***' : null,
            'recipient_id' => $recipientId ? substr($recipientId, 0, 4) . '***' : null,
            'timestamp' => $timestamp,
        ]);
        $payloadRedacted = $event;
        if (isset($payloadRedacted['message']['text'])) {
            $payloadRedacted['message']['text'] = '[REDACTED]';
        }
        InstagramWebhookEvent::create([
            'instagram_connection_id' => $connection->id,
            'event_type' => $eventType,
            'mid' => $mid,
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'event_timestamp' => $timestamp ? (strlen((string)(int)$timestamp) > 10 ? \Carbon\Carbon::createFromTimestampMs((int) $timestamp) : \Carbon\Carbon::createFromTimestamp((int) $timestamp)) : null,
            'payload_redacted' => $payloadRedacted,
        ]);
    }

    protected function saveIncomingMessage(
        InstagramConnection $connection,
        string $senderId,
        ?string $mid,
        string $text,
        array $attachments
    ): void {
        if ($mid && InstagramMessage::where('instagram_connection_id', $connection->id)->where('instagram_message_id', $mid)->exists()) {
            return;
        }
        $customer = $this->findOrCreateCustomerByIgId($connection, $senderId);
        $mediaUrl = null;
        $mediaMime = null;
        if (!empty($attachments[0]['payload']['url'])) {
            $mediaUrl = $attachments[0]['payload']['url'];
            $mediaMime = $attachments[0]['type'] ?? null;
        }
        InstagramMessage::create([
            'instagram_connection_id' => $connection->id,
            'instagram_message_id' => $mid,
            'ig_user_id' => $senderId,
            'from_username' => null,
            'message' => $text,
            'message_type' => $mediaUrl ? 'attachment' : 'text',
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMime,
            'customer_id' => $customer?->id,
            'direction' => 'incoming',
            'status' => 'received',
        ]);
    }

    protected function findOrCreateCustomerByIgId(InstagramConnection $connection, string $igUserId): ?Customer
    {
        $contact = CustomerContact::where('type', 'instagram')->where('value', $igUserId)->first();
        if ($contact) {
            return $contact->customer;
        }

        $username = null;
        $profile = app(MetaInstagramService::class)->getUserProfile($connection, $igUserId);
        if (empty($profile['error']) && !empty($profile['username'])) {
            $username = trim($profile['username']);
        }

        $instagramType = SocialMediaType::where('name', 'Instagram')->first();
        if ($instagramType && $username !== null && $username !== '') {
            $normalized = strtolower(ltrim($username, '@'));
            $candidates = CustomerSocialMedia::where('social_media_type_id', $instagramType->id)->get();
            $social = $candidates->first(fn ($c) => strtolower(ltrim($c->handle ?? '', '@')) === $normalized);
            if ($social) {
                CustomerContact::create([
                    'customer_id' => $social->customer_id,
                    'type' => 'instagram',
                    'value' => $igUserId,
                ]);
                return $social->customer;
            }
        }

        // فرستنده در مخاطبان نیست: مشتری جدید ساخته نمی‌شود؛ پیام با customer_id=null ذخیره می‌شود و بعداً می‌توان تخصیص یا ثبت دستی انجام داد.
        return null;
    }
}
