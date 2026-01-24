# راهنمای Deploy به سرور

## مراحل Deploy

### 1. Pull تغییرات از Git
```bash
git pull origin main
```

### 2. نصب Dependencies (در صورت نیاز)
```bash
composer install --no-dev --optimize-autoloader
npm install
```

### 3. Build فایل‌های Frontend (مهم!)
```bash
npm run build
```

این دستور فایل‌های Vue.js را compile می‌کند و در `public/build` قرار می‌دهد.

### 4. Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### 5. Run Migrations (در صورت نیاز)
```bash
php artisan migrate --force
```

## نکات مهم:

1. **همیشه بعد از pull کردن تغییرات، `npm run build` را اجرا کنید**
2. فایل‌های build در `public/build` قرار می‌گیرند
3. اگر دکمه‌ها یا تغییرات frontend دیده نمی‌شوند، احتمالاً build نشده‌اند
4. در production، از `npm run build` استفاده کنید (نه `npm run dev`)

## Troubleshooting:

اگر بعد از build هم تغییرات دیده نمی‌شوند:
- Cache مرورگر را پاک کنید (Ctrl+Shift+R)
- بررسی کنید که `APP_ENV=production` در `.env` تنظیم شده باشد
- بررسی کنید که `VITE_APP_URL` در `.env` درست تنظیم شده باشد
