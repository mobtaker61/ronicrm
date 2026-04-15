<?php

namespace App\Services;

use App\Exceptions\GoogleContactsPhotoSyncInterruptedException;
use App\Exceptions\GoogleContactsSyncInterruptedException;
use App\Models\Customer;
use App\Models\GoogleContactsIntegration;
use App\Support\FullNameParser;
use App\Support\GoogleContactsBulkSyncCancel;
use App\Support\GoogleContactsPhotoBulkCancel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleContactsSyncService
{
    /** @var array<string, true> */
    protected array $importEmailIndex = [];

    /** @var array<string, true> */
    protected array $importPhoneIndex = [];

    public function __construct(
        protected GoogleContactsOAuthService $oauth
    ) {}

    /**
     * @param  callable|null  $afterEach  function (int $processed, int $total, int $success, int $failed, ?string $lastError): void
     * @return array{success: int, failed: int, errors: array<int, string>, total: int, cancelled: bool, processed: int}
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
                'cancelled' => false,
                'processed' => 0,
            ];
        }

        $total = (int) Customer::query()->count();
        $success = 0;
        $failed = 0;
        $errors = [];
        $processed = 0;
        $cancelled = false;

        foreach (Customer::query()->with('contacts')->orderBy('id')->lazyById(100) as $customer) {
            if (GoogleContactsBulkSyncCancel::requested()) {
                GoogleContactsBulkSyncCancel::clear();
                $cancelled = true;
                break;
            }

            $lastError = null;
            try {
                $this->pushCustomer($customer, $token);
                $success++;
            } catch (GoogleContactsSyncInterruptedException $e) {
                GoogleContactsBulkSyncCancel::clear();
                $cancelled = true;
                $processed++;
                if ($afterEach !== null) {
                    $afterEach($processed, $total, $success, $failed, $lastError);
                }

                break;
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
            try {
                $this->throttleCrmToGoogleAfterCustomer($processed, $total);
            } catch (GoogleContactsSyncInterruptedException $e) {
                GoogleContactsBulkSyncCancel::clear();
                $cancelled = true;
                break;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'total' => $total,
            'cancelled' => $cancelled,
            'processed' => $processed,
        ];
    }

    /**
     * برای همهٔ مشتریانی که به مخاطب Google لینک شده‌اند، تصویر را از Google می‌گیرد و در CRM ذخیره می‌کند.
     *
     * @param  callable|null  $afterEach  function (int $processed, int $total, int $updated, int $skipped, int $failed, ?string $lastError): void
     * @return array{updated: int, skipped: int, failed: int, errors: array<int, string>, total: int, cancelled: bool, processed: int}
     */
    public function syncAllLinkedCustomerPhotosFromGoogle(?callable $afterEach = null): array
    {
        $token = $this->oauth->getValidAccessToken();
        if (! $token) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['Google Contacts is not connected or token refresh failed.'],
                'total' => 0,
                'cancelled' => false,
                'processed' => 0,
            ];
        }

        $baseQuery = Customer::query()
            ->whereNotNull('google_people_resource_name')
            ->where('google_people_resource_name', '!=', '');

        $total = (int) Customer::query()
            ->whereNotNull('google_people_resource_name')
            ->where('google_people_resource_name', '!=', '')
            ->count();
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $processed = 0;
        $cancelled = false;

        foreach ($baseQuery->orderBy('id')->lazyById(100) as $customer) {
            if (GoogleContactsPhotoBulkCancel::requested()) {
                GoogleContactsPhotoBulkCancel::clear();
                $cancelled = true;
                break;
            }

            $lastError = null;
            $resourceName = (string) $customer->google_people_resource_name;
            try {
                if ($this->applyGooglePhotoBytesToCustomer($customer, $token, $resourceName, null)) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (GoogleContactsPhotoSyncInterruptedException $e) {
                GoogleContactsPhotoBulkCancel::clear();
                $cancelled = true;
                $processed++;
                if ($afterEach !== null) {
                    $afterEach($processed, $total, $updated, $skipped, $failed, $lastError);
                }

                break;
            } catch (\Throwable $e) {
                $failed++;
                $lastError = "Customer #{$customer->id} ({$customer->name}): ".$e->getMessage();
                $errors[] = $lastError;
                Log::warning('Google photo sync row failed', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $processed++;
            if ($afterEach !== null) {
                $afterEach($processed, $total, $updated, $skipped, $failed, $lastError);
            }
            try {
                $this->throttlePhotoBulkAfterCustomer($processed, max(1, $total));
            } catch (GoogleContactsPhotoSyncInterruptedException $e) {
                GoogleContactsPhotoBulkCancel::clear();
                $cancelled = true;
                break;
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'total' => $total,
            'cancelled' => $cancelled,
            'processed' => $processed,
        ];
    }

    /**
     * @throws \RuntimeException
     */
    public function pushCustomer(Customer $customer, ?string $accessToken = null): void
    {
        $this->abortIfBulkSyncCancelled();

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

        $companyLabel = trim((string) ($customer->company_name ?: ''));
        if ($companyLabel === '' && $customer->type === 'company') {
            $companyLabel = trim((string) $customer->name);
        }

        if ($customer->type === 'company') {
            if ($companyLabel === '') {
                $companyLabel = trim((string) $customer->name) ?: 'Company';
            }
            $nameObj = $this->buildGoogleNameStructForCompany($customer, $companyLabel);
            $organizations = [
                ['name' => $companyLabel],
            ];
        } else {
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
            $organizations = [];
            if ($companyLabel !== '') {
                $org = ['name' => $companyLabel];
                if (! empty(trim((string) $customer->contact_person))) {
                    $org['title'] = trim((string) $customer->contact_person);
                }
                $organizations = [$org];
            }
        }

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
        if ($organizations !== []) {
            $person['organizations'] = $organizations;
        }

        return $person;
    }

    /**
     * برای مشتری نوع شرکت: نام نمایشی Google از contact_person (نام شخص) و سازمان از نام شرکت.
     *
     * @return array<string, mixed>
     */
    protected function buildGoogleNameStructForCompany(Customer $customer, string $companyLabel): array
    {
        $cp = trim((string) $customer->contact_person);
        if ($cp !== '') {
            $parsed = FullNameParser::parse($cp);
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

            return $nameObj;
        }

        return [
            'givenName' => $companyLabel,
            'displayName' => $companyLabel,
        ];
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
            $this->abortIfBulkSyncCancelled();
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
                $this->sleepForGoogleQuotaExceeded($patch, 'crm_sync');

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
            $this->abortIfBulkSyncCancelled();
            $last = Http::withToken($token)->timeout(90)->get($url, $query);

            if ($last->successful() || $last->status() === 404) {
                return $last;
            }

            if ($last->status() === 429 && $i < $max - 1) {
                $this->sleepForGoogleQuotaExceeded($last, 'crm_sync');

                continue;
            }

            return $last;
        }

        return $last ?? Http::response('', 500);
    }

    /**
     * @param  'none'|'crm_sync'|'photo'  $interrupt
     */
    protected function sleepForGoogleQuotaExceeded(\Illuminate\Http\Client\Response $response, string $interrupt = 'none'): void
    {
        $retryAfter = $response->header('Retry-After');
        $base = (int) config('services.google_contacts.quota_retry_base_seconds', 65);
        $seconds = is_numeric($retryAfter) ? (int) $retryAfter : $base;
        $seconds = max(5, min($seconds, 180));

        Log::info('Google People API rate/quota limit; waiting before retry', [
            'seconds' => $seconds,
            'status' => $response->status(),
            'interrupt' => $interrupt,
        ]);

        if ($interrupt === 'crm_sync') {
            $this->sleepSecondsWithSyncCancelCheck($seconds);
        } elseif ($interrupt === 'photo') {
            $this->sleepSecondsWithPhotoCancelCheck($seconds);
        } else {
            sleep($seconds);
        }
    }

    protected function abortIfBulkSyncCancelled(): void
    {
        if (GoogleContactsBulkSyncCancel::requested()) {
            throw new GoogleContactsSyncInterruptedException;
        }
    }

    protected function abortIfPhotoBulkCancelled(): void
    {
        if (GoogleContactsPhotoBulkCancel::requested()) {
            throw new GoogleContactsPhotoSyncInterruptedException;
        }
    }

    protected function sleepSecondsWithSyncCancelCheck(int $seconds): void
    {
        $seconds = max(0, min($seconds, 300));
        $until = time() + $seconds;
        while (time() < $until) {
            $this->abortIfBulkSyncCancelled();
            $left = $until - time();
            if ($left <= 0) {
                break;
            }
            sleep(min(1, $left));
        }
    }

    protected function sleepSecondsWithPhotoCancelCheck(int $seconds): void
    {
        $seconds = max(0, min($seconds, 300));
        $until = time() + $seconds;
        while (time() < $until) {
            $this->abortIfPhotoBulkCancelled();
            $left = $until - time();
            if ($left <= 0) {
                break;
            }
            sleep(min(1, $left));
        }
    }

    /**
     * @throws GoogleContactsSyncInterruptedException
     */
    protected function sleepMsWithSyncCancelCheck(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }
        $remainingUs = $ms * 1000;
        $step = 200_000;
        while ($remainingUs > 0) {
            $this->abortIfBulkSyncCancelled();
            $u = (int) min($step, $remainingUs);
            usleep($u);
            $remainingUs -= $u;
        }
    }

    /**
     * @throws GoogleContactsPhotoSyncInterruptedException
     */
    protected function sleepMsWithPhotoCancelCheck(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }
        $remainingUs = $ms * 1000;
        $step = 200_000;
        while ($remainingUs > 0) {
            $this->abortIfPhotoBulkCancelled();
            $u = (int) min($step, $remainingUs);
            usleep($u);
            $remainingUs -= $u;
        }
    }

    /**
     * بین هر مشتری در همگام‌سازی CRM→Google؛ قابل قطع با sync-stop.
     *
     * @throws GoogleContactsSyncInterruptedException
     */
    protected function throttleCrmToGoogleAfterCustomer(int $processed, int $total): void
    {
        $ms = (int) config('services.google_contacts.bulk_sync_delay_ms', 700);
        if ($ms <= 0 || $processed >= $total) {
            return;
        }

        $this->sleepMsWithSyncCancelCheck($ms);
    }

    /**
     * بین هر ردیف به‌روزرسانی انبوه عکس؛ قابل قطع.
     *
     * @throws GoogleContactsPhotoSyncInterruptedException
     */
    protected function throttlePhotoBulkAfterCustomer(int $processed, int $total): void
    {
        $ms = (int) config('services.google_contacts.bulk_sync_delay_ms', 700);
        if ($ms <= 0 || $processed >= $total) {
            return;
        }

        $this->sleepMsWithPhotoCancelCheck($ms);
    }

    /**
     * واردات مخاطبین از Google (لیست connections) به CRM.
     *
     * @param  callable|null  $afterEach  function (int $processed, int $total, int $imported, int $skippedDuplicate, int $skippedEmpty, int $failed, ?string $lastError): void
     * @return array{imported: int, skipped_duplicate: int, skipped_empty: int, failed: int, errors: array<int, string>, total: int}
     */
    public function importAllFromGoogle(?callable $afterEach = null): array
    {
        $token = $this->oauth->getValidAccessToken();
        if (! $token) {
            return [
                'imported' => 0,
                'skipped_duplicate' => 0,
                'skipped_empty' => 0,
                'failed' => 0,
                'errors' => ['Google Contacts is not connected or token refresh failed.'],
                'total' => 0,
            ];
        }

        $this->importEmailIndex = [];
        $this->importPhoneIndex = [];
        $this->buildImportLookupIndex();

        $imported = 0;
        $skippedDuplicate = 0;
        $skippedEmpty = 0;
        $failed = 0;
        $errors = [];
        $processed = 0;
        $totalPeople = 0;
        $pageToken = null;
        $max = (int) config('services.google_contacts.quota_max_retries', 5);

        do {
            $query = [
                'personFields' => 'names,emailAddresses,phoneNumbers,organizations,photos',
                'pageSize' => 1000,
            ];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = null;
            for ($attempt = 0; $attempt < $max; $attempt++) {
                $response = Http::withToken($token)->timeout(120)->get('https://people.googleapis.com/v1/people/me/connections', $query);

                if ($response->successful() || $response->status() === 404) {
                    break;
                }

                if ($response->status() === 429 && $attempt < $max - 1) {
                    $this->sleepForGoogleQuotaExceeded($response);

                    continue;
                }

                break;
            }

            if ($response === null || (! $response->successful() && $response->status() !== 404)) {
                return [
                    'imported' => $imported,
                    'skipped_duplicate' => $skippedDuplicate,
                    'skipped_empty' => $skippedEmpty,
                    'failed' => $failed,
                    'errors' => array_merge($errors, ['connections.list: '.($response ? $response->body() : 'no response')]),
                    'total' => $totalPeople > 0 ? $totalPeople : $processed,
                ];
            }

            $body = $response->json();
            if ($totalPeople === 0) {
                $totalPeople = (int) ($body['totalPeople'] ?? $body['totalSize'] ?? 0);
            }

            $connections = $body['connections'] ?? [];
            $pageToken = isset($body['nextPageToken']) && is_string($body['nextPageToken']) ? $body['nextPageToken'] : null;

            foreach ($connections as $person) {
                if (! is_array($person)) {
                    continue;
                }

                $processed++;
                $lastError = null;
                $runningTotal = $totalPeople > 0 ? $totalPeople : $processed;

                try {
                    $outcome = $this->importOneGooglePerson($person, $token);
                    if ($outcome === 'imported') {
                        $imported++;
                    } elseif ($outcome === 'skipped_duplicate') {
                        $skippedDuplicate++;
                    } elseif ($outcome === 'skipped_empty') {
                        $skippedEmpty++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $lastError = $e->getMessage();
                    $errors[] = $lastError;
                    Log::warning('Google Contacts import row failed', [
                        'error' => $lastError,
                    ]);
                }

                if ($afterEach !== null) {
                    $afterEach($processed, $runningTotal, $imported, $skippedDuplicate, $skippedEmpty, $failed, $lastError);
                }
            }

            if ($pageToken !== null) {
                $ms = (int) config('services.google_contacts.bulk_sync_delay_ms', 700);
                if ($ms > 0) {
                    usleep($ms * 1000);
                }
            }
        } while ($pageToken !== null);

        $total = $totalPeople > 0 ? $totalPeople : $processed;

        return [
            'imported' => $imported,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_empty' => $skippedEmpty,
            'failed' => $failed,
            'errors' => $errors,
            'total' => $total,
        ];
    }

    protected function buildImportLookupIndex(): void
    {
        Customer::query()
            ->with(['contacts' => function ($q) {
                $q->whereIn('type', ['email', 'phone', 'whatsapp']);
            }])
            ->orderBy('id')
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    $this->registerCustomerInImportIndex($customer);
                }
            });
    }

    protected function registerCustomerInImportIndex(Customer $customer): void
    {
        $e = strtolower(trim((string) $customer->email));
        if ($e !== '') {
            $this->importEmailIndex[$e] = true;
        }

        $pd = preg_replace('/\D+/', '', (string) $customer->phone) ?? '';
        if ($pd !== '') {
            $this->importPhoneIndex[$pd] = true;
        }

        foreach ($customer->contacts as $contact) {
            if ($contact->type === 'email') {
                $ev = strtolower(trim((string) $contact->value));
                if ($ev !== '') {
                    $this->importEmailIndex[$ev] = true;
                }
            }
            if (in_array($contact->type, ['phone', 'whatsapp'], true)) {
                $d = preg_replace('/\D+/', '', (string) $contact->value) ?? '';
                if ($d !== '') {
                    $this->importPhoneIndex[$d] = true;
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int, string>  $phones
     */
    protected function googlePersonMatchesExisting(array $emails, array $phones): bool
    {
        foreach ($emails as $e) {
            $k = strtolower(trim($e));
            if ($k !== '' && isset($this->importEmailIndex[$k])) {
                return true;
            }
        }
        foreach ($phones as $phone) {
            $d = preg_replace('/\D+/', '', $phone) ?? '';
            if ($d !== '' && isset($this->importPhoneIndex[$d])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int, string>  $phones
     */
    protected function registerImportedIdentifiers(array $emails, array $phones): void
    {
        foreach ($emails as $e) {
            $k = strtolower(trim($e));
            if ($k !== '') {
                $this->importEmailIndex[$k] = true;
            }
        }
        foreach ($phones as $p) {
            $d = preg_replace('/\D+/', '', $p) ?? '';
            if ($d !== '') {
                $this->importPhoneIndex[$d] = true;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $person
     * @return 'imported'|'skipped_duplicate'|'skipped_empty'
     */
    protected function importOneGooglePerson(array $person, string $token): string
    {
        $resourceName = $person['resourceName'] ?? '';
        if (! is_string($resourceName) || $resourceName === '') {
            throw new \RuntimeException('Missing resourceName.');
        }

        $integration = GoogleContactsIntegration::getSingleton();
        $userId = $integration?->connected_by;

        $existingByGoogleId = Customer::query()->where('google_people_resource_name', $resourceName)->first();
        if ($existingByGoogleId) {
            $this->syncImportedCustomerAvatarFromGoogle($existingByGoogleId, $person, $token, $resourceName);

            return 'skipped_duplicate';
        }

        $emails = $this->extractGooglePersonEmails($person);
        $phones = $this->extractGooglePersonPhones($person);

        $nameParts = is_array($person['names'][0] ?? null) ? $person['names'][0] : [];
        $origDisplay = trim((string) ($nameParts['displayName'] ?? ''));
        if ($origDisplay === '') {
            $origDisplay = trim((string) (($nameParts['givenName'] ?? '').' '.($nameParts['familyName'] ?? '')));
        }
        if ($this->stringLooksLikePhone($origDisplay) && $origDisplay !== '') {
            if (! $this->phoneDigitsListContains($phones, $origDisplay)) {
                $phones[] = $origDisplay;
            }
        }

        $emails = $this->uniqueEmailStrings($emails);
        $phones = $this->uniquePhoneStrings($phones);

        if ($this->googlePersonMatchesExisting($emails, $phones)) {
            $match = $this->findFirstCustomerMatchingGooglePerson($emails, $phones);
            if ($match) {
                if (empty($match->google_people_resource_name)) {
                    $match->update([
                        'google_people_resource_name' => $resourceName,
                        'updated_by' => $userId,
                    ]);
                }
                $this->syncImportedCustomerAvatarFromGoogle($match, $person, $token, $resourceName);
            }

            return 'skipped_duplicate';
        }

        if ($emails === [] && $phones === []) {
            return 'skipped_empty';
        }

        $resolved = $this->resolveGoogleImportCrmFields($person, $nameParts, $origDisplay, $emails, $phones);

        $primaryEmail = $resolved['primary_email'];
        $primaryPhone = $resolved['primary_phone'];
        $extraEmails = $resolved['extra_emails'];
        $extraPhones = $resolved['extra_phones'];

        $customer = Customer::create([
            'name' => $resolved['name'],
            'type' => $resolved['type'],
            'company_name' => $resolved['company_name'],
            'contact_person' => $resolved['contact_person'],
            'email' => $primaryEmail,
            'phone' => $primaryPhone,
            'status' => 'lead',
            'source' => 'other',
            'notes' => 'Imported from Google Contacts.',
            'google_people_resource_name' => $resourceName,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach ($extraEmails as $e) {
            $customer->contacts()->create([
                'type' => 'email',
                'value' => $e,
                'is_primary' => false,
            ]);
        }
        foreach ($extraPhones as $p) {
            $customer->contacts()->create([
                'type' => 'phone',
                'value' => $p,
                'is_primary' => false,
            ]);
        }

        $this->registerImportedIdentifiers($emails, $phones);

        $this->syncImportedCustomerAvatarFromGoogle($customer, $person, $token, $resourceName);

        return 'imported';
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $nameParts
     * @param  array<int, string>  $emails
     * @param  array<int, string>  $phones
     * @return array{type: string, name: string, company_name: ?string, contact_person: ?string, primary_email: ?string, primary_phone: ?string, extra_emails: array<int, string>, extra_phones: array<int, string>}
     */
    protected function resolveGoogleImportCrmFields(array $person, array $nameParts, string $origDisplay, array $emails, array $phones): array
    {
        $orgs = $person['organizations'] ?? [];
        $orgNameRaw = '';
        $orgTitleRaw = '';
        if (is_array($orgs) && isset($orgs[0]) && is_array($orgs[0])) {
            $orgNameRaw = trim((string) ($orgs[0]['name'] ?? ''));
            $orgTitleRaw = trim((string) ($orgs[0]['title'] ?? ''));
        }

        $given = trim((string) ($nameParts['givenName'] ?? ''));
        $family = trim((string) ($nameParts['familyName'] ?? ''));
        $hasGivenFamily = $given !== '' || $family !== '';
        $composed = trim($given.' '.$family);

        $orgName = $orgNameRaw;
        $orgTitle = $orgTitleRaw;
        if ($orgName === '' && $orgTitle !== '' && ! $hasGivenFamily) {
            $orgName = $orgTitle;
            $orgTitle = '';
        }

        $companyAsContact = $orgName !== '' && ! $hasGivenFamily
            && ($origDisplay === '' || $this->stringLooksLikePhone($origDisplay) || strcasecmp($origDisplay, $orgName) === 0);

        $primaryEmail = $emails[0] ?? null;
        $primaryPhone = $phones[0] ?? null;
        $extraEmails = array_slice($emails, 1);
        $extraPhones = array_slice($phones, 1);

        if ($companyAsContact) {
            return [
                'type' => 'company',
                'name' => $orgName,
                'company_name' => $orgName,
                'contact_person' => $this->pickContactPersonFromOrgTitle($orgTitle, $orgName),
                'primary_email' => $primaryEmail,
                'primary_phone' => $primaryPhone,
                'extra_emails' => $extraEmails,
                'extra_phones' => $extraPhones,
            ];
        }

        $name = '';
        if ($composed !== '') {
            $name = $composed;
        } elseif ($origDisplay !== '' && ! $this->stringLooksLikePhone($origDisplay)) {
            $name = $origDisplay;
        } elseif ($orgName !== '') {
            $name = $orgName;
        } elseif ($emails !== []) {
            $local = explode('@', (string) $emails[0], 2)[0];
            $name = trim($local) !== '' ? trim($local) : 'Google contact';
        } elseif ($phones !== []) {
            $name = 'Contact ('.$this->phoneSuffixForLabel($phones[0]).')';
        } else {
            $name = 'Google contact';
        }

        $companyName = $orgName !== '' ? $orgName : null;
        $contactPerson = null;
        if ($orgTitle !== '' && ! $this->stringLooksLikePhone($orgTitle)
            && strcasecmp($orgTitle, (string) $orgName) !== 0 && mb_strlen($orgTitle) > 2) {
            $contactPerson = $orgTitle;
        }

        return [
            'type' => 'person',
            'name' => $name,
            'company_name' => $companyName,
            'contact_person' => $contactPerson,
            'primary_email' => $primaryEmail,
            'primary_phone' => $primaryPhone,
            'extra_emails' => $extraEmails,
            'extra_phones' => $extraPhones,
        ];
    }

    protected function pickContactPersonFromOrgTitle(string $orgTitle, string $orgName): ?string
    {
        if ($orgTitle === '' || $this->stringLooksLikePhone($orgTitle)) {
            return null;
        }
        if ($orgName !== '' && strcasecmp($orgTitle, $orgName) === 0) {
            return null;
        }
        if (mb_strlen($orgTitle) <= 2) {
            return null;
        }

        return $orgTitle;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<int, string>
     */
    protected function extractGooglePersonEmails(array $person): array
    {
        $emails = [];
        foreach ($person['emailAddresses'] ?? [] as $ea) {
            if (! is_array($ea)) {
                continue;
            }
            $v = $ea['value'] ?? '';
            if (is_string($v) && trim($v) !== '') {
                $emails[] = trim($v);
            }
        }

        return $emails;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<int, string>
     */
    protected function extractGooglePersonPhones(array $person): array
    {
        $phones = [];
        foreach ($person['phoneNumbers'] ?? [] as $pn) {
            if (! is_array($pn)) {
                continue;
            }
            $v = $pn['value'] ?? '';
            if (is_string($v) && trim($v) !== '') {
                $phones[] = trim($v);
            }
        }

        return $phones;
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    protected function uniqueEmailStrings(array $emails): array
    {
        $seen = [];
        $out = [];
        foreach ($emails as $e) {
            $k = strtolower(trim($e));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = trim($e);
        }

        return array_values($out);
    }

    /**
     * @param  array<int, string>  $phones
     * @return array<int, string>
     */
    protected function uniquePhoneStrings(array $phones): array
    {
        $seen = [];
        $out = [];
        foreach ($phones as $p) {
            $k = preg_replace('/\D+/', '', $p) ?? '';
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $p;
        }

        return array_values($out);
    }

    protected function stringLooksLikePhone(string $s): bool
    {
        $t = trim($s);
        if ($t === '') {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $t) ?? '';
        if (strlen($digits) < 7) {
            return false;
        }
        $nonPhone = preg_replace('/[\d\s\-\+\(\)\.\u{00A0}]+/u', '', $t) ?? '';

        return $nonPhone === '' || mb_strlen($nonPhone) <= 2;
    }

    /**
     * @param  array<int, string>  $phones
     */
    protected function phoneDigitsListContains(array $phones, string $candidate): bool
    {
        $d = preg_replace('/\D+/', '', $candidate) ?? '';
        if ($d === '') {
            return false;
        }
        foreach ($phones as $p) {
            if ((preg_replace('/\D+/', '', $p) ?? '') === $d) {
                return true;
            }
        }

        return false;
    }

    protected function phoneSuffixForLabel(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($d) >= 4) {
            return substr($d, -4);
        }

        return $d !== '' ? $d : '?';
    }

    /**
     * پس از ایجاد/تطبیق مشتری با Google، در صورت وجود عکس واقعی (غیر پیش‌فرض) در disk public ذخیره می‌شود.
     */
    protected function syncImportedCustomerAvatarFromGoogle(Customer $customer, array $person, string $token, string $resourceName): void
    {
        try {
            $this->applyGooglePhotoBytesToCustomer($customer, $token, $resourceName, $person);
        } catch (GoogleContactsPhotoSyncInterruptedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::debug('Google import avatar skipped', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $person  اگر null باشد فقط از API خوانده می‌شود.
     * @return bool true اگر فایل جدید ذخیره شد
     */
    protected function applyGooglePhotoBytesToCustomer(Customer $customer, string $token, string $resourceName, ?array $person): bool
    {
        $this->abortIfPhotoBulkCancelled();

        $photoUrl = null;
        if ($person !== null) {
            $photoUrl = $this->selectGoogleContactPhotoUrl($person);
        }
        if ($photoUrl === null) {
            $photoUrl = $this->fetchGoogleContactPhotoUrlFromApi($token, $resourceName);
        }
        if ($photoUrl === null) {
            return false;
        }

        $bytes = $this->downloadGooglePhotoBytes($token, $photoUrl);
        if ($bytes === null || $bytes === '') {
            return false;
        }

        return $this->storeDownloadedAvatarOnCustomer($customer, $bytes);
    }

    protected function storeDownloadedAvatarOnCustomer(Customer $customer, string $bytes): bool
    {
        if (strlen($bytes) > 3 * 1024 * 1024) {
            Log::debug('Google avatar skip: too large', ['customer_id' => $customer->id]);

            return false;
        }

        $ext = $this->guessImageExtensionFromBytes($bytes);
        if ($ext === null) {
            Log::debug('Google avatar skip: not a recognized image', ['customer_id' => $customer->id]);

            return false;
        }

        $relativePath = 'avatars/g-import-'.Str::uuid()->toString().'.'.$ext;
        $old = $customer->avatar;
        Storage::disk('public')->put($relativePath, $bytes);
        $customer->update([
            'avatar' => $relativePath,
            'updated_by' => $customer->updated_by,
        ]);
        if (is_string($old) && $old !== '' && $old !== $relativePath && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        return true;
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int, string>  $phones
     */
    protected function findFirstCustomerMatchingGooglePerson(array $emails, array $phones): ?Customer
    {
        foreach ($emails as $e) {
            $norm = strtolower(trim($e));
            if ($norm === '') {
                continue;
            }
            $c = $this->findCustomerByNormalizedEmail($norm);
            if ($c) {
                return $c;
            }
        }
        foreach ($phones as $p) {
            $digits = preg_replace('/\D+/', '', $p) ?? '';
            if ($digits === '' || strlen($digits) < 7) {
                continue;
            }
            $c = $this->findCustomerByNormalizedPhoneDigits($digits);
            if ($c) {
                return $c;
            }
        }

        return null;
    }

    protected function findCustomerByNormalizedEmail(string $norm): ?Customer
    {
        $byMain = Customer::query()
            ->whereRaw('LOWER(TRIM(COALESCE(email, ?))) = ?', ['', $norm])
            ->first();

        if ($byMain) {
            return $byMain;
        }

        return Customer::query()
            ->whereHas('contacts', function ($q) use ($norm) {
                $q->where('type', 'email')
                    ->whereRaw('LOWER(TRIM(value)) = ?', [$norm]);
            })
            ->first();
    }

    protected function findCustomerByNormalizedPhoneDigits(string $digits): ?Customer
    {
        foreach (Customer::query()
            ->with(['contacts' => function ($q) {
                $q->whereIn('type', ['phone', 'whatsapp']);
            }])
            ->orderBy('id')
            ->lazyById(150) as $customer) {
            if ($this->customerHasNormalizedPhoneDigits($customer, $digits)) {
                return $customer;
            }
        }

        return null;
    }

    protected function customerHasNormalizedPhoneDigits(Customer $customer, string $digits): bool
    {
        $main = preg_replace('/\D+/', '', (string) $customer->phone) ?? '';
        if ($main !== '' && $main === $digits) {
            return true;
        }
        foreach ($customer->contacts as $contact) {
            $d = preg_replace('/\D+/', '', (string) $contact->value) ?? '';
            if ($d !== '' && $d === $digits) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    protected function selectGoogleContactPhotoUrl(array $person): ?string
    {
        $photos = $person['photos'] ?? [];
        if (! is_array($photos)) {
            return null;
        }

        foreach ($photos as $photo) {
            if (! is_array($photo)) {
                continue;
            }
            if (! empty($photo['default'])) {
                continue;
            }
            $u = $photo['url'] ?? '';
            if (! is_string($u) || trim($u) === '') {
                continue;
            }

            return $this->googlePhotoUrlWithSize($u, 512);
        }

        return null;
    }

    protected function googlePhotoUrlWithSize(string $url, int $size): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[?&]sz=\d+/i', $url)) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'sz='.$size;
    }

    protected function fetchGoogleContactPhotoUrlFromApi(string $token, string $resourceName): ?string
    {
        $max = (int) config('services.google_contacts.quota_max_retries', 5);
        $url = 'https://people.googleapis.com/v1/'.$resourceName;
        $query = ['personFields' => 'photos'];

        $last = null;
        for ($i = 0; $i < $max; $i++) {
            $this->abortIfPhotoBulkCancelled();
            $last = Http::withToken($token)->timeout(60)->get($url, $query);

            if ($last->successful()) {
                $json = $last->json();

                return is_array($json) ? $this->selectGoogleContactPhotoUrl($json) : null;
            }

            if ($last->status() === 429 && $i < $max - 1) {
                $this->sleepForGoogleQuotaExceeded($last, 'photo');

                continue;
            }

            return null;
        }

        return null;
    }

    protected function downloadGooglePhotoBytes(string $token, string $photoUrl): ?string
    {
        $response = Http::withToken($token)
            ->timeout(45)
            ->withHeaders(['Accept' => 'image/*,*/*'])
            ->get($photoUrl);

        if ($response->successful() && $response->body() !== '') {
            return $response->body();
        }

        $fallback = Http::timeout(45)
            ->withHeaders(['Accept' => 'image/*,*/*'])
            ->get($photoUrl);

        if ($fallback->successful() && $fallback->body() !== '') {
            return $fallback->body();
        }

        Log::debug('Google photo download failed', [
            'status' => $response->status(),
            'fallback_status' => $fallback->status(),
        ]);

        return null;
    }

    protected function guessImageExtensionFromBytes(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (strlen($bytes) >= 12 && str_starts_with($bytes, 'RIFF') && str_starts_with(substr($bytes, 8, 4), 'WEBP')) {
            return 'webp';
        }
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'gif';
        }

        return null;
    }
}
