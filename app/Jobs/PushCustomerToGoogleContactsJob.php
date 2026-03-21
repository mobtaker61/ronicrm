<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\GoogleContactsIntegration;
use App\Services\GoogleContactsSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushCustomerToGoogleContactsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct(public int $customerId) {}

    public function uniqueId(): string
    {
        return 'google-contact-push-'.$this->customerId;
    }

    public function handle(GoogleContactsSyncService $sync): void
    {
        if (! GoogleContactsIntegration::getSingleton()) {
            return;
        }

        $customer = Customer::query()->with('contacts')->find($this->customerId);
        if (! $customer) {
            return;
        }

        try {
            $sync->pushCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning('Push customer to Google Contacts failed', [
                'customer_id' => $this->customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
