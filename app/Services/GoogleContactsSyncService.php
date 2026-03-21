<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\FullNameParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleContactsSyncService
{
    public function __construct(
        protected GoogleContactsOAuthService $oauth
    ) {}

    /**
     * @return array{success: int, failed: int, errors: array<int, string>}
     */
    public function syncAllCustomers(): array
    {
        $token = $this->oauth->getValidAccessToken();
        if (! $token) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Google Contacts is not connected or token refresh failed.']];
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        Customer::query()->with('contacts')->orderBy('id')->chunkById(100, function ($customers) use ($token, &$success, &$failed, &$errors) {
            foreach ($customers as $customer) {
                try {
                    $this->pushCustomer($customer, $token);
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Customer #{$customer->id} ({$customer->name}): ".$e->getMessage();
                    Log::warning('Google Contacts sync row failed', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
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
                    'type' => $c->type === 'whatsapp' ? 'mobile' : 'mobile',
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
        $get = Http::withToken($token)->get('https://people.googleapis.com/v1/'.$resourceName, [
            'personFields' => 'metadata,names,emailAddresses,phoneNumbers,biographies,organizations',
        ]);

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
            ->asJson()
            ->patch($url, $person);

        if (! $patch->successful()) {
            throw new \RuntimeException('updateContact: '.$patch->body());
        }
    }
}
