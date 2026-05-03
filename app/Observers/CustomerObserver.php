<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\OrganizationNotificationService;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        // جلوگیری از اسپم در importهای حجیم و عملیات کنسولی
        if (app()->runningInConsole()) {
            return;
        }
        try {
            $route = request()?->route();
            if ($route && method_exists($route, 'getName')) {
                $name = (string) ($route->getName() ?? '');
                if (in_array($name, ['customers.import', 'customers.import-preview'], true)) {
                    return;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        // بعد از پاسخ HTTP انجام شود تا ایجاد مخاطب کند نشود
        try {
            $customerId = (int) $customer->id;
            $orgId = (int) ($customer->organization_id ?? 0);
            app()->terminating(function () use ($customerId, $orgId) {
                try {
                    if ($orgId > 0) {
                        \App\Support\OrganizationContext::setOrganizationId($orgId);
                    }
                    $cust = Customer::withoutGlobalScopes()->with('organization')->find($customerId);
                    if ($cust) {
                        app(OrganizationNotificationService::class)->handleCustomerCreated($cust);
                    }
                } catch (\Throwable $e) {
                    Log::warning('CustomerObserver notification failed', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (\Throwable) {
            // ignore
        }
    }
}

