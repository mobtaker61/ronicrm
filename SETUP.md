# راهنمای راه‌اندازی RoniCRM

## پیش‌نیازها

- PHP >= 8.2
- Composer
- Node.js >= 18
- MySQL/PostgreSQL
- Laragon (برای محیط توسعه)

## نصب و راه‌اندازی

### 1. نصب Dependencies

```bash
composer install
npm install
```

### 2. تنظیمات محیط

فایل `.env` را کپی کنید:

```bash
cp .env.example .env
```

سپس فایل `.env` را ویرایش کنید و تنظیمات زیر را اضافه کنید:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ronicrm
DB_USERNAME=root
DB_PASSWORD=

# RoniBot WhatsApp API
RONIBOT_API_URL=https://api.ronibot.com
RONIBOT_API_KEY=your_api_key_here

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ronicrm.com
MAIL_FROM_NAME="${APP_NAME}"

# Queue Configuration
QUEUE_CONNECTION=database
```

### 3. تولید کلید برنامه

```bash
php artisan key:generate
```

### 4. اجرای Migration ها

```bash
php artisan migrate
```

### 5. Seed کردن داده‌های اولیه

برای ایجاد کاربران و نقش‌ها:

```bash
php artisan db:seed
```

برای ایجاد داده‌های نمونه (مشتریان، صنایع، کمپین‌ها):

```bash
php artisan db:seed --class=SampleDataSeeder
```

یا برای seed کردن همه چیز با داده‌های نمونه:

```bash
php artisan db:seed --with-sample-data
```

### 6. Build کردن Frontend

```bash
npm run build
```

یا برای development mode:

```bash
npm run dev
```

### 7. راه‌اندازی سرور

```bash
php artisan serve
```

یا اگر از Laragon استفاده می‌کنید، پروژه را در Laragon اضافه کنید.

## اطلاعات ورود پیش‌فرض

بعد از اجرای `php artisan db:seed`، می‌توانید با اطلاعات زیر وارد شوید:

**Admin:**
- Email: `admin@ronicrm.com`
- Password: `password`

**User:**
- Email: `user@ronicrm.com`
- Password: `password`

## راه‌اندازی Queue Worker

برای پردازش کمپین‌های زمان‌بندی شده و ارسال پیام‌ها، باید Queue Worker را اجرا کنید:

```bash
php artisan queue:work
```

یا برای اجرای دائمی در background:

```bash
php artisan queue:work --daemon
```

### تنظیم Cron Job (برای کمپین‌های زمان‌بندی شده)

در فایل `crontab` یا Task Scheduler ویندوز، این خط را اضافه کنید:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

یا در Windows Task Scheduler، یک task ایجاد کنید که هر دقیقه این دستور را اجرا کند:

```bash
php artisan schedule:run
```

## تنظیمات RoniBot API

1. به پنل RoniBot بروید و API Key خود را دریافت کنید
2. در فایل `.env`، `RONIBOT_API_URL` و `RONIBOT_API_KEY` را تنظیم کنید

## تنظیمات SMTP

برای استفاده از Mailtrap (سرویس رایگان برای تست):

1. به [Mailtrap](https://mailtrap.io) بروید و یک حساب رایگان ایجاد کنید
2. در بخش SMTP Settings، اطلاعات را کپی کنید
3. در فایل `.env`، تنظیمات MAIL را وارد کنید

برای استفاده از SMTP واقعی (Gmail, Outlook و...):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
```

## ساختار پروژه

```
ronicrm/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # کنترلرها
│   │   └── Middleware/        # میدلورها
│   ├── Models/                # مدل‌ها
│   └── Services/              # سرویس‌ها (WhatsApp, Email)
├── database/
│   ├── migrations/            # Migration ها
│   └── seeders/               # Seeder ها
├── resources/
│   ├── js/
│   │   ├── Pages/             # صفحات Vue
│   │   └── Layouts/           # Layout ها
│   └── views/                 # Blade Templates
└── routes/
    └── web.php                # Route ها
```

## ویژگی‌های سیستم

- ✅ مدیریت مشتریان (CRUD)
- ✅ دسته‌بندی بر اساس صنعت، وضعیت و منبع
- ✅ مدیریت کمپین‌های واتساپ و ایمیل
- ✅ سیستم Queue برای ارسال انبوه
- ✅ گزارش‌گیری و آمار
- ✅ داشبورد آماری
- ✅ سیستم نقش‌ها و دسترسی‌ها

## عیب‌یابی

### مشکل در Build Frontend

```bash
npm run build
```

اگر خطا داشتید، dependencies را دوباره نصب کنید:

```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

### مشکل در Route Helper

اگر `route()` در Vue components کار نمی‌کند، مطمئن شوید که:

1. `npm run build` را اجرا کرده‌اید
2. فایل `resources/views/app.blade.php` شامل `@routes` است
3. `window.Ziggy` در `resources/js/bootstrap.js` تنظیم شده است

### مشکل در Queue

اگر Queue کار نمی‌کند:

1. مطمئن شوید که `QUEUE_CONNECTION=database` در `.env` است
2. Migration های queue را اجرا کنید: `php artisan queue:table`
3. Queue Worker را اجرا کنید: `php artisan queue:work`

## پشتیبانی

برای سوالات و مشکلات، لطفاً issue ایجاد کنید یا با تیم توسعه تماس بگیرید.
