# RoniCRM

سیستم مدیریت ارتباط با مشتری (CRM) مبتنی بر Laravel با قابلیت ارسال پیام‌های واتساپ و ایمیل

## ویژگی‌ها

- ✅ مدیریت کامل مشتریان (CRUD)
- ✅ دسته‌بندی بر اساس صنعت، وضعیت و منبع
- ✅ مدیریت کمپین‌های واتساپ و ایمیل
- ✅ سیستم Queue برای ارسال انبوه پیام‌ها
- ✅ گزارش‌گیری و آمار پیشرفته
- ✅ داشبورد آماری
- ✅ سیستم نقش‌ها و دسترسی‌ها (RBAC)
- ✅ اینباکس واتساپ
- ✅ کارت مشتری قابل اشتراک‌گذاری

## تکنولوژی‌های استفاده شده

- **Backend**: Laravel 12
- **Frontend**: Vue 3 + Inertia.js
- **Styling**: Tailwind CSS 4
- **Database**: MySQL/PostgreSQL
- **Queue**: Database Queue
- **Permissions**: Spatie Laravel Permission

## پیش‌نیازها

- PHP >= 8.2
- Composer
- Node.js >= 18
- MySQL/PostgreSQL
- Git

## نصب و راه‌اندازی

### 1. کلون کردن Repository

```bash
git clone https://github.com/YOUR_USERNAME/ronicrm.git
cd ronicrm
```

### 2. نصب Dependencies

```bash
composer install
npm install
```

### 3. تنظیمات محیط

فایل `.env` را از `.env.example` کپی کنید:

```bash
cp .env.example .env
```

سپس فایل `.env` را ویرایش کنید و تنظیمات پایگاه داده و API را اضافه کنید.

### 4. تولید کلید برنامه

```bash
php artisan key:generate
```

### 5. اجرای Migration ها

```bash
php artisan migrate
```

### 6. Seed کردن داده‌های اولیه

```bash
php artisan db:seed
```

### 7. Build کردن Frontend

```bash
npm run build
```

### 8. راه‌اندازی سرور

```bash
php artisan serve
```

برای اطلاعات بیشتر، فایل [SETUP.md](SETUP.md) را مطالعه کنید.

## استقرار در سرور (Deployment)

برای راهنمایی کامل استقرار در cPanel، فایل [DEPLOY.md](DEPLOY.md) را مطالعه کنید.

## ساختار پروژه

```
ronicrm/
├── app/
│   ├── Http/Controllers/      # کنترلرها
│   ├── Models/                 # مدل‌ها
│   └── Services/               # سرویس‌ها
├── database/
│   ├── migrations/             # Migration ها
│   └── seeders/                # Seeder ها
├── resources/
│   ├── js/                     # فایل‌های Vue.js
│   └── views/                  # Blade Templates
└── routes/
    └── web.php                 # Route ها
```

## مجوز

این پروژه تحت مجوز MIT منتشر شده است.

## پشتیبانی

برای سوالات و مشکلات، لطفاً یک Issue ایجاد کنید.
