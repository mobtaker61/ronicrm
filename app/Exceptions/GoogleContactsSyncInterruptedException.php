<?php

namespace App\Exceptions;

/**
 * پایه برای توقف عملیات طولانی Google (همگام‌سازی CRM→Google یا به‌روزرسانی انبوه عکس).
 */
abstract class GoogleContactsOperationInterruptedException extends \RuntimeException {}

/**
 * همگام‌سازی انبوه CRM → Google توسط کاربر متوقف شد.
 */
class GoogleContactsSyncInterruptedException extends GoogleContactsOperationInterruptedException
{
    public function __construct(string $message = 'Sync stopped by user.')
    {
        parent::__construct($message);
    }
}

/**
 * به‌روزرسانی انبوه عکس از Google توسط کاربر متوقف شد.
 */
class GoogleContactsPhotoSyncInterruptedException extends GoogleContactsOperationInterruptedException
{
    public function __construct(string $message = 'Photo sync stopped by user.')
    {
        parent::__construct($message);
    }
}
