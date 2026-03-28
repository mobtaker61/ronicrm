<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeRegisteredNotification extends Notification
{
    public function __construct(
        public Organization $organization
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = config('app.name', 'RoniCRM');

        return (new MailMessage)
            ->subject('خوش آمدید — '.$name)
            ->line('ثبت‌نام شما با موفقیت انجام شد.')
            ->line('سازمان: '.$this->organization->name)
            ->action('ورود به پنل', url(route('dashboard', [], true)));
    }
}
