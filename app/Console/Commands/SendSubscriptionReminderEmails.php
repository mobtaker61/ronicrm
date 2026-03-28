<?php

namespace App\Console\Commands;

use App\Models\OrganizationSubscription;
use App\Notifications\SubscriptionExpiringNotification;
use App\Services\SubscriptionService;
use App\Support\PlatformNotificationSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendSubscriptionReminderEmails extends Command
{
    protected $signature = 'subscriptions:send-reminder-emails';

    protected $description = 'ارسال ایمیل یادآوری اشتراک/آزمایشی به مالک سازمان بر اساس تنظیمات پلتفرم';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $settings = PlatformNotificationSettings::get();
        if (! $settings['email_org_subscription_reminder']) {
            $this->info('یادآوری اشتراک در تنظیمات پلتفرم غیرفعال است.');

            return self::SUCCESS;
        }

        $daysList = $settings['subscription_reminder_days'] ?? [7, 3, 1];
        $sent = 0;

        OrganizationSubscription::query()
            ->with(['organization.owner'])
            ->chunkById(100, function ($subs) use ($subscriptionService, $daysList, &$sent): void {
                foreach ($subs as $sub) {
                    $org = $sub->organization;
                    if (! $org || ! $org->owner?->email) {
                        continue;
                    }

                    $status = $subscriptionService->computeStatus($sub);
                    if (! in_array($status, ['trial', 'active'], true)) {
                        continue;
                    }

                    $remaining = $subscriptionService->remainingDays($sub);
                    if ($remaining === null || ! in_array($remaining, $daysList, true)) {
                        continue;
                    }

                    $phase = $status === 'trial' ? 'trial' : 'paid';
                    $end = $status === 'trial' ? $sub->trial_ends_at : $sub->ends_at;
                    $endKey = $end ? $end->toIso8601String() : 'none';
                    $cacheKey = 'sub_reminder_email:'.$sub->organization_id.':'.$endKey.':'.$phase.':'.$remaining.'d';

                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    try {
                        $org->owner->notify(new SubscriptionExpiringNotification($org, $sub, $remaining, $phase === 'trial' ? 'trial' : 'paid'));
                        Cache::put($cacheKey, true, now()->addDays(120));
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning('Subscription reminder email failed: '.$e->getMessage(), [
                            'organization_id' => $org->id,
                        ]);
                    }
                }
            });

        $this->info("ارسال انجام شد: {$sent} ایمیل.");

        return self::SUCCESS;
    }
}
