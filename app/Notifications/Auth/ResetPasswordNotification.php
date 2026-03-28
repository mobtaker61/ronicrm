<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $name = config('app.name', 'RoniCRM');

        return (new MailMessage)
            ->subject('بازیابی رمز عبور — '.$name)
            ->line('درخواست بازنشانی رمز عبور برای حساب شما ثبت شد.')
            ->action('تنظیم رمز جدید', $url)
            ->line('این لینک تا '.$minutes.' دقیقه معتبر است.')
            ->line('اگر این درخواست از طرف شما نبوده، این ایمیل را نادیده بگیرید.');
    }
}
