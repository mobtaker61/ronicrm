<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification
{
    public function __construct(
        public Organization $organization,
        public OrganizationSubscription $subscription,
        public int $remainingDays,
        public string $phase
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = config('app.name', 'RoniCRM');
        $phaseLabel = $this->phase === 'trial' ? 'دوره آزمایشی' : 'اشتراک';

        return (new MailMessage)
            ->subject('یادآوری '.$phaseLabel.' — '.$this->organization->name)
            ->line('سازمان: '.$this->organization->name)
            ->line('تعداد روزهای باقی‌مانده تا پایان '.$phaseLabel.': '.$this->remainingDays)
            ->action('ورود به تنظیمات', url(route('settings.index', ['tab' => 'subscription'], true)));
    }
}
