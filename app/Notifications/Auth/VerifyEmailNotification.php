<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        $name = config('app.name', 'RoniCRM');

        return (new MailMessage)
            ->subject('تأیید آدرس ایمیل — '.$name)
            ->line('لطفاً با کلیک روی دکمهٔ زیر، آدرس ایمیل خود را تأیید کنید.')
            ->action('تأیید ایمیل', $url)
            ->line('اگر شما این حساب را ایجاد نکرده‌اید، نیازی به اقدام نیست.');
    }
}
