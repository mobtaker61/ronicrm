<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\FullNameParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleContactsSyncService
{
    public function __construct(
        protected GoogleContactsOAuthService $oauth
    ) {}

    /**
     * @param  callable|null  $afterEach  function (int $processed, int $total, int $success, int $failed, ?string $lastError): void
     * @return array{success: int, failed: int, errors: array<int, string>, total: int}
     */
    public function syncAllCustomers(?callable $afterEach = null): array
    {
        $token = $this->oauth->getValidAccessToken();
        if (! $token) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => ['Google Contacts is not connected or token refresh failed.'],
                'total' => 0,
            ];
        }

        $total = (int) Customer::query()->count();
        $success = 0;
        $failed = 0;
        $errors = [];
        $processed = 0;

        Customer::query()->with('contacts')->orderBy('id')->chunkById(100, function ($customers) use ($token, $afterEach, $total, &$success, &$failed, &$errors, &$processed) {
            foreach ($customers as $customer) {
                $lastError = null;
                try {
                    $this->pushCustomer($customer, $token);
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $lastError = "Customer #{$customer->id} ({$customer->name}): ".$e->getMessage();
                    $errors[] = $lastError;
                    Log::warning('Google Contacts sync row failed', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $processed++;
                if ($afterEach !== null) {
                    $afterEach($processed, $total, $success, $failed, $lastError);
                }
                $this->throttleBulkSyncAfterCustomer($processed, $total);
            }
        });

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'total' => $total,
        ];
    }

    /**
     * @throws \RuntimeException
     */
    public function pushCustomer(Customer $customer, ?string $accessToken = null): void
    {
        $token = $accessToken ?? $this->oauth->getValidAccessToken();
        if (! $token) {
            throw new \RuntimeException('No valid Google access token.');
        }

        $customer->loadMissing('contacts');

        $person = $this->buildPersonPayload($customer);

        $resourceName = $customer->google_people_resource_name;

        if ($resourceName) {
            $this->updateContact($token, $resourceName, $person, $customer);
        } else {
            $this->createContact($token, $person, $customer);
        }

        $customer->refresh();
        $rn = $customer->google_people_resource_name;
        if (is_string($rn) && $rn !== '') {
            $this->syncContactPhoto($token, $rn, $customer);
        }
    }

    protected function syncContactPhoto(string $token, string $resourceName, Customer $customer): void
    {
        if (empty($customer->avatar)) {
            return;
        }

        $path = $customer->avatar;
        if (! Storage::disk('public')->exists($path)) {
            Log::debug('Google contact photo skip: file missing', ['path' => $path]);

            return;
        }

        $bytes = Storage::disk('public')->get($path);
        if ($bytes === false || $bytes === '') {
            return;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return;
        }

        $b64 = base64_encode($bytes);
        if (strlen($b64) > 6_000_000) {
            Log::warning('Google contact photo skip: too large', ['customer_id' => $customer->id]);

            return;
        }

        $url = 'https://people.googleapis.com/v1/'.$resourceName.':updateContactPhoto';

        $response = Http::withToken($token)
            ->asJson()
            ->patch($url, [
                'photoBytes' => $b64,
            ]);

        if (! $response->successful()) {
            Log::warning('Google updateContactPhoto failed', [
                'customer_id' => $customer->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    protected function buildPersonPayload(Customer $customer): array
    {
        $parsed = FullNameParser::parse($customer->name);

        $nameObj = [
            'givenName' => $parsed['given'],
            'displayName' => $parsed['display'],
        ];
        if (! empty($parsed['middle'])) {
            $nameObj['middleName'] = $parsed['middle'];
        }
        if (! empty($parsed['family'])) {
            $nameObj['familyName'] = $parsed['family'];
        }

        $emails = [];
        if (! empty($customer->email)) {
            $emails[] = ['value' => $customer->email, 'type' => 'work'];
        }
        foreach ($customer->contacts as $c) {
            if ($c->type === 'email' && ! empty($c->value)) {
                $emails[] = ['value' => $c->value, 'type' => 'other'];
            }
        }
        $emails = $this->uniqueEmails($emails);

        $phones = [];
        if (! empty($customer->phone)) {
            $phones[] = ['value' => $customer->phone, 'type' => 'mobile'];
        }
        foreach ($customer->contacts as $c) {
            if (in_array($c->type, ['phone', 'whatsapp'], true) && ! empty($c->value)) {
                $phones[] = [
                    'value' => $c->value,
                    'type' => 'mobile',
                ];
            }
        }
        $phones = $this->uniquePhones($phones);

        $person = [
            'names' => [$nameObj],
            'biographies' => [
                ['value' => 'RoniCRM customer #'.$customer->id.(trim((string) $customer->notes) !== '' ? "\n".mb_substr(strip_tags($customer->notes), 0, 500) : ''), 'contentType' => 'TEXT_PLAIN'],
            ],
        ];

        if ($emails !== []) {
            $person['emailAddresses'] = $emails;
        }
        if ($phones !== []) {
            $person['phoneNumbers'] = $phones;
        }

        if ($customer->type === 'company' && ! empty($customer->company_name)) {
            $org = ['name' => $customer->company_name];
            if (! empty($customer->contact_person)) {
                $org['title'] = $customer->contact_person;
            }
            $person['organizations'] = [$org];
        } elseif (! empty($customer->company_name)) {
            $person['organizations'] = [
                ['name' => $customer->company_name],
            ];
        }

        return $person;
    }

    /**
     * @param  array<int, array{value: string, type: string}>  $emails
     * @return array<int, array{value: string, type: string}>
     */
    protected function uniqueEmails(array $emails): array
    {
        $seen = [];
        $out = [];
        foreach ($emails as $e) {
            $k = strtolower(trim($e['value']));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $e;
        }

        return $out;
    }

    /**
     * @param  array<int, array{value: string, type: string}>  $phones
     * @return array<int, array{value: string, type: string}>
     */
    protected function uniquePhones(array $phones): array
    {
        $seen = [];
        $out = [];
        foreach ($phones as $p) {
            $k = preg_replace('/\D+/', '', $p['value']) ?? '';
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $p;
        }

        return $out;
    }

    protected function createContact(string $token, array $person, Customer $customer): void
    {
        $response = Http::withToken($token)
            ->asJson()
            ->post('https://people.googleapis.com/v1/people:createContact', $person);

        if (! $response->successful()) {
            throw new \RuntimeException('createContact: '.$response->body());
        }

        $resourceName = $response->json('resourceName');
        if (! is_string($resourceName) || $resourceName === '') {
            throw new \RuntimeException('createContact: missing resourceName in response.');
        }

        $data = ['google_people_resource_name' => $resourceName];
        if (auth()->check()) {
            $data['updated_by'] = auth()->id();
        }
        $customer->update($data);
    }

    protected function updateContact(string $token, string $resourceName, array $person, Customer $customer): void
    {
        $max = (int) config('services.google_contacts.quota_max_retries', 5);

        for ($attempt = 0; $attempt < $max; $attempt++) {
            $get = $this->peopleGetContact($token, $resourceName);

            if ($get->status() === 404) {
                $customer->update(['google_people_resource_name' => null]);
                $this->createContact($token, $person, $customer->fresh());

                return;
            }

            if (! $get->successful()) {
                throw new \RuntimeException('getContact: '.$get->body());
            }

            $etag = $get->json('etag');
            if (! is_string($etag) || $etag === '') {
                throw new \RuntimeException('getContact: missing etag.');
            }

            $person['etag'] = $etag;

            $fieldList = ['names', 'emailAddresses', 'phoneNumbers', 'biographies'];
            if (! empty($person['organizations'])) {
                $fieldList[] = 'organizations';
            }
            $fields = implode(',', $fieldList);
            $url = 'https://people.googleapis.com/v1/'.$resourceName.':updateContact?updatePersonFields='.$fields;

            $patch = Http::withToken($token)
                ->timeout(90)
                ->asJson()
                ->patch($url, $person);

            if ($patch->successful()) {
                return;
            }

            if ($patch->status() === 429 && $attempt < $max - 1) {
                $this->sleepForGoogleQuotaExceeded($patch);

                continue;
            }

            throw new \RuntimeException('updateContact: '.$patch->body());
        }
    }

    /**
     * GET مخاطب با تلاش مجدد روی ۴۲۹ (Critical read quota).
     */
    protected function peopleGetContact(string $token, string $resourceName): \Illuminate\Http\Client\Response
    {
        $max = (int) config('services.google_contacts.quota_max_retries', 5);
        $url = 'https://people.googleapis.com/v1/'.$resourceName;
        $query = [
            'personFields' => 'metadata,names,emailAddresses,phoneNumbers,biographies,organizations',
        ];

        $last = null;
        for ($i = 0; $i < $max; $i++) {
            $last = Http::withToken($token)->timeout(90)->get($url, $query);

            if ($last->successful() || $last->status() === 404) {
                return $last;
            }

            if ($last->status() === 429 && $i < $max - 1) {
                $this->sleepForGoogleQuotaExceeded($last);

                continue;
            }

            return $last;
        }

        return $last ?? Http::response('', 500);
    }

    protected function sleepForGoogleQuotaExceeded(\Illuminate\Http\Client\Response $response): void
    {
        $retryAfter = $response->header('Retry-After');
        $base = (int) config('services.google_contacts.quota_retry_base_seconds', 65);
        $seconds = is_numeric($retryAfter) ? (int) $retryAfter : $base;
        $seconds = max(5, min($seconds, 180));

        Log::info('Google People API rate/quota limit; waiting before retry', [
            'seconds' => $seconds,
            'status' => $response->status(),
        ]);

        sleep($seconds);
    }

    /**
     * بین هر مشتری در همگام‌سازی انبوه صبر می‌کند تا از سقف Critical read/minute عبور نکنیم.
     */
    protected function throttleBulkSyncAfterCustomer(int $processed, int $total): void
    {
        $ms = (int) config('services.google_contacts.bulk_sync_delay_ms', 700);
        if ($ms <= 0 || $processed >= $total) {
            return;
        }

        usleep($ms * 1000);
    }
}
