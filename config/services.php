<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ronibot' => [
        'url' => env('RONIBOT_API_URL', ''),
        'key' => env('RONIBOT_API_KEY', ''),
    ],

    'meta_instagram' => [
        'client_id' => env('META_APP_ID', env('INSTAGRAM_APP_ID', '')),
        'client_secret' => env('META_APP_SECRET', env('INSTAGRAM_APP_SECRET', '')),
        'redirect_uri' => env('META_REDIRECT_URI', ''),
        'verify_token' => env('META_VERIFY_TOKEN', ''),
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
    ],

    'telegram' => [
        'api_id' => env('TELEGRAM_API_ID', ''),
        'api_hash' => env('TELEGRAM_API_HASH', ''),
        /** حداکثر زمان یک عملیات MadelineProto در حلقهٔ رویداد (ثانیه) */
        'madeline_run_timeout' => max(60, (int) env('MADELINE_PROTO_RUN_TIMEOUT', 180)),
        /** مدت نگه‌داشتن قفل Cache برای session (ثانیه) */
        'madeline_cache_lock_ttl' => max(120, (int) env('MADELINE_PROTO_CACHE_LOCK_TTL', 600)),
        /** حداکثر انتظار برای گرفتن قفل Cache قبل از خطا (ثانیه) — ارسال Madeline ممکن است دقیقه‌ها طول بکشد */
        'madeline_cache_lock_block' => max(30, (int) env('MADELINE_PROTO_CACHE_LOCK_BLOCK', 420)),
        /**
         * true = هنگام ساخت API از حالت «نمونه کامل» استفاده شود نه کلاینت IPC (جلوگیری از Channel closed / hang).
         * فقط وقتی telegram:listen-incoming خاموش است باید از وب/cron Madeline زده شود.
         */
        'madeline_force_full_instance' => filter_var(env('MADELINE_PROTO_FORCE_FULL', true), FILTER_VALIDATE_BOOL),
        /** دور زدن باگ Undefined array key در getFullDialogs — true = از getDialogIds استفاده کن */
        'madeline_use_get_dialog_ids' => filter_var(env('MADELINE_USE_GET_DIALOG_IDS', true), FILTER_VALIDATE_BOOL),
    ],

    'google_contacts' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI') ?: (rtrim((string) env('APP_URL', ''), '/').'/settings/google-contacts/callback'),
        /** میلی‌ثانیه بین هر مشتری در همگام‌سازی انبوه؛ سقف People API حدود ۹۰ Critical read/دقیقه است (پیش‌فرض ~۸۵/دقیقه) */
        'bulk_sync_delay_ms' => (int) env('GOOGLE_CONTACTS_BULK_SYNC_DELAY_MS', 700),
        /** تعداد تلاش مجدد بعد از ۴۲۹ */
        'quota_max_retries' => max(1, (int) env('GOOGLE_CONTACTS_QUOTA_MAX_RETRIES', 5)),
        /** اگر هدر Retry-After نباشد، چند ثانیه صبر کنیم */
        'quota_retry_base_seconds' => max(5, min(180, (int) env('GOOGLE_CONTACTS_QUOTA_RETRY_BASE_SECONDS', 65))),
    ],

];
