<?php

namespace App\Support;

use App\Models\Setting;

class PlatformNotificationSettings
{
    public const SETTING_KEY = 'platform_notifications';

    /**
     * @return array{
     *   owner_emails: list<string>,
     *   email_user_welcome: bool,
     *   email_owner_new_registration: bool,
     *   email_user_password_reset: bool,
     *   email_org_subscription_reminder: bool,
     *   subscription_reminder_days: list<int>
     * }
     */
    public static function defaults(): array
    {
        return [
            'owner_emails' => [],
            'email_user_welcome' => true,
            'email_owner_new_registration' => true,
            'email_user_password_reset' => true,
            'email_org_subscription_reminder' => true,
            'subscription_reminder_days' => [7, 3, 1],
        ];
    }

    public static function get(): array
    {
        $stored = Setting::get(self::SETTING_KEY, []);
        $merged = array_merge(self::defaults(), is_array($stored) ? $stored : []);
        $days = $merged['subscription_reminder_days'] ?? [7, 3, 1];
        $merged['subscription_reminder_days'] = array_values(array_unique(array_map('intval', is_array($days) ? $days : [7, 3, 1])));
        $owners = $merged['owner_emails'] ?? [];
        $merged['owner_emails'] = is_array($owners) ? array_values(array_filter($owners, fn ($e) => is_string($e) && filter_var(trim($e), FILTER_VALIDATE_EMAIL))) : [];

        return $merged;
    }

    /**
     * @return list<string>
     */
    public static function ownerEmails(): array
    {
        $emails = self::get()['owner_emails'] ?? [];
        $env = env('MAIL_PLATFORM_OWNER_EMAILS', '');
        if (is_string($env) && $env !== '') {
            foreach (preg_split('/[\s,;]+/', $env, -1, PREG_SPLIT_NO_EMPTY) as $e) {
                $emails[] = trim($e);
            }
        }

        $out = [];
        foreach ($emails as $e) {
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out));
    }
}
