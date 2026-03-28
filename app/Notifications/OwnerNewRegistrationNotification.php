<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerNewRegistrationNotification extends Notification
{
    public function __construct(
        public User $user,
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
            ->subject('ثبت‌نام سازمان جدید — '.$name)
            ->line('کاربر: '.$this->user->name)
            ->line('نام کاربری: '.$this->user->username)
            ->line('ایمیل: '.$this->user->email)
            ->line('سازمان: '.$this->organization->name.' ('.$this->organization->slug.')');
    }
}
