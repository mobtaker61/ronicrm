<?php

namespace App\Support\TranslationSeedFragments;

/**
 * Auth / registration copy for unified login page.
 */
final class AuthRegisterKeys
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function definitions(): array
    {
        return [
            'tab_login' => [
                'en' => 'Sign in',
                'fa' => 'ورود',
                'ar' => 'تسجيل الدخول',
                'tr' => 'Giriş',
                'ku' => 'چوونەژوورەوە',
            ],
            'tab_register' => [
                'en' => 'Create organization',
                'fa' => 'ثبت سازمان',
                'ar' => 'إنشاء مؤسسة',
                'tr' => 'Kuruluş oluştur',
                'ku' => 'دروستکردنی ڕێکخراو',
            ],
            'welcome_back' => [
                'en' => 'Welcome back',
                'fa' => 'خوش برگشتید',
                'ar' => 'مرحبًا بعودتك',
                'tr' => 'Tekrar hoş geldiniz',
                'ku' => 'بەخێربێیتەوە',
            ],
            'sign_in_subtitle' => [
                'en' => 'Sign in to your workspace.',
                'fa' => 'به فضای کاری خود وارد شوید.',
                'ar' => 'سجّل الدخول إلى مساحة عملك.',
                'tr' => 'Çalışma alanınıza giriş yapın.',
                'ku' => 'بچۆ ناو ناوچەی کارەکەت.',
            ],
            'register_subtitle' => [
                'en' => 'Create your organization and owner account. You can invite team members later from settings.',
                'fa' => 'سازمان و حساب مالک را بسازید. اعضای تیم را بعداً از تنظیمات اضافه کنید.',
                'ar' => 'أنشئ مؤسستك وحساب المالك. يمكنك دعوة الفريق لاحقًا من الإعدادات.',
                'tr' => 'Kuruluşunuzu ve sahip hesabını oluşturun. Ekibi sonra ayarlardan ekleyebilirsiniz.',
                'ku' => 'ڕێکخراو و هەژماری خاوەن دروست بکە. دواتر لە ڕێکخستنەکان ئەندام زیاد بکە.',
            ],
            'organization_name' => [
                'en' => 'Organization name',
                'fa' => 'نام سازمان',
                'ar' => 'اسم المؤسسة',
                'tr' => 'Kuruluş adı',
                'ku' => 'ناوی ڕێکخراو',
            ],
            'owner_full_name' => [
                'en' => 'Your full name (owner)',
                'fa' => 'نام و نام خانوادگی شما (مالک)',
                'ar' => 'اسمك الكامل (المالك)',
                'tr' => 'Adınız soyadınız (sahip)',
                'ku' => 'ناوی تەواوت (خاوەن)',
            ],
            'username' => [
                'en' => 'Username',
                'fa' => 'نام کاربری',
                'ar' => 'اسم المستخدم',
                'tr' => 'Kullanıcı adı',
                'ku' => 'ناوی بەکارهێنەر',
            ],
            'register_email' => [
                'en' => 'Email',
                'fa' => 'ایمیل',
                'ar' => 'البريد الإلكتروني',
                'tr' => 'E-posta',
                'ku' => 'ئیمەیڵ',
            ],
            'password_confirmation' => [
                'en' => 'Confirm password',
                'fa' => 'تکرار رمز عبور',
                'ar' => 'تأكيد كلمة المرور',
                'tr' => 'Şifre tekrarı',
                'ku' => 'دووبارەکردنەوەی وشەی نهێنی',
            ],
            'create_account' => [
                'en' => 'Create organization & sign in',
                'fa' => 'ایجاد سازمان و ورود',
                'ar' => 'إنشاء المؤسسة وتسجيل الدخول',
                'tr' => 'Kuruluşu oluştur ve giriş yap',
                'ku' => 'ڕێکخراو دروست بکە و بچۆ ژوورەوە',
            ],
            'creating_account' => [
                'en' => 'Creating…',
                'fa' => 'در حال ایجاد…',
                'ar' => 'جارٍ الإنشاء…',
                'tr' => 'Oluşturuluyor…',
                'ku' => 'دروست دەکرێت…',
            ],
            'have_account' => [
                'en' => 'Already have an account?',
                'fa' => 'قبلاً ثبت‌نام کرده‌اید؟',
                'ar' => 'لديك حساب بالفعل؟',
                'tr' => 'Zaten hesabınız var mı?',
                'ku' => 'پێشتر هەژمارت هەیە؟',
            ],
            'need_organization' => [
                'en' => 'Need a new organization?',
                'fa' => 'سازمان جدید می‌خواهید؟',
                'ar' => 'تحتاج مؤسسة جديدة؟',
                'tr' => 'Yeni kuruluş mu gerekiyor?',
                'ku' => 'ڕێکخراوی نوێ دەتەوێت؟',
            ],
        ];
    }
}
