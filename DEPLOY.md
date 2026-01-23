# راهنمای استقرار RoniCRM در cPanel

این راهنما به شما کمک می‌کند تا پروژه RoniCRM را از طریق GitHub در سرور cPanel استقرار دهید.

## پیش‌نیازها

- حساب cPanel با دسترسی SSH
- دسترسی به Git در سرور
- حساب GitHub
- PHP >= 8.2 در سرور
- Composer در سرور
- Node.js >= 18 در سرور (برای build)

## مرحله 1: آماده‌سازی پروژه برای GitHub

### 1.1. بررسی فایل‌های حساس

مطمئن شوید که فایل `.env` در `.gitignore` قرار دارد و هرگز به GitHub push نمی‌شود.

### 1.2. Initialize کردن Git Repository

```bash
cd "d:\Site Developing\ronicrm"
git init
```

### 1.3. اضافه کردن فایل‌ها به Git

```bash
git add .
```

### 1.4. Commit اولیه

```bash
git commit -m "Initial commit: RoniCRM project"
```

## مرحله 2: ایجاد Repository در GitHub

### 2.1. ورود به GitHub

1. به [GitHub.com](https://github.com) بروید و وارد حساب کاربری خود شوید
2. روی دکمه **"+"** در بالای صفحه کلیک کنید
3. گزینه **"New repository"** را انتخاب کنید

### 2.2. تنظیمات Repository

- **Repository name**: `ronicrm` (یا نام دلخواه)
- **Description**: `CRM System with WhatsApp and Email Campaigns`
- **Visibility**: Private یا Public (طبق نیاز شما)
- **Initialize this repository with**: هیچ کدام را تیک نزنید (چون پروژه از قبل آماده است)

### 2.3. ایجاد Repository

روی دکمه **"Create repository"** کلیک کنید.

## مرحله 3: اتصال پروژه محلی به GitHub

### 3.1. اضافه کردن Remote

بعد از ایجاد repository در GitHub، دستورات زیر را اجرا کنید:

```bash
git remote add origin https://github.com/YOUR_USERNAME/ronicrm.git
```

**توجه**: `YOUR_USERNAME` را با نام کاربری GitHub خود جایگزین کنید.

### 3.2. تغییر نام Branch به main (اگر لازم باشد)

```bash
git branch -M main
```

### 3.3. Push کردن به GitHub

```bash
git push -u origin main
```

اگر از HTTPS استفاده می‌کنید، GitHub از شما نام کاربری و رمز عبور (یا Personal Access Token) می‌خواهد.

## مرحله 4: تنظیمات در cPanel

### 4.1. ورود به cPanel

1. وارد حساب cPanel خود شوید
2. به بخش **"Terminal"** یا **"SSH Access"** بروید

### 4.2. دسترسی به SSH

اگر Terminal در cPanel ندارید، می‌توانید از SSH Client استفاده کنید:
- Windows: PuTTY یا Windows Terminal
- Mac/Linux: Terminal

### 4.3. پیدا کردن مسیر Document Root

معمولاً مسیر Document Root در cPanel یکی از این موارد است:
- `/home/username/public_html`
- `/home/username/domain.com/public_html`

برای پیدا کردن مسیر دقیق:
```bash
pwd
# یا
echo $HOME
```

## مرحله 5: Clone کردن Repository در سرور

### 5.1. رفتن به مسیر مناسب

```bash
cd ~/public_html
# یا
cd ~/domain.com/public_html
```

### 5.2. Clone کردن Repository

```bash
git clone https://github.com/YOUR_USERNAME/ronicrm.git .
```

**توجه**: نقطه (`.`) در انتهای دستور به این معنی است که فایل‌ها مستقیماً در پوشه فعلی clone شوند.

اگر می‌خواهید در یک پوشه جداگانه clone کنید:

```bash
git clone https://github.com/YOUR_USERNAME/ronicrm.git ronicrm
cd ronicrm
```

## مرحله 6: تنظیمات پروژه در سرور

### 6.1. نصب Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install
```

### 6.2. ایجاد فایل .env

```bash
cp .env.example .env
```

### 6.3. ویرایش فایل .env

از ویرایشگر متن در cPanel یا nano/vim استفاده کنید:

```bash
nano .env
```

تنظیمات مهم:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# سایر تنظیمات...
```

### 6.4. تولید کلید برنامه

```bash
php artisan key:generate
```

### 6.5. اجرای Migration ها

```bash
php artisan migrate --force
```

### 6.6. Seed کردن داده‌های اولیه (اختیاری)

```bash
php artisan db:seed
```

### 6.7. Build کردن Frontend

```bash
npm run build
```

### 6.8. تنظیم مجوزها

```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 6.9. ایجاد Symbolic Link برای Storage

```bash
php artisan storage:link
```

## مرحله 7: تنظیمات cPanel

### 7.1. تنظیم Document Root

اگر پروژه در پوشه `public` قرار دارد:

1. در cPanel به **"File Manager"** بروید
2. فایل `.htaccess` را در root پیدا کنید یا ایجاد کنید
3. محتوای زیر را اضافه کنید:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

یا می‌توانید Document Root را مستقیماً به پوشه `public` تغییر دهید.

### 7.2. تنظیم PHP Version

در cPanel به **"Select PHP Version"** بروید و PHP >= 8.2 را انتخاب کنید.

### 7.3. تنظیم Queue Worker و Scheduler

**⚠️ مهم:** برای اجرای صحیح Queue Worker و Scheduler، لطفاً راهنمای کامل `QUEUE_SETUP.md` را مطالعه کنید.

#### روش 1: استفاده از Supervisor (توصیه می‌شود)

1. فایل `supervisor-ronicrm-queue-worker.conf` را کپی کنید:
   ```bash
   sudo cp supervisor-ronicrm-queue-worker.conf /etc/supervisor/conf.d/ronicrm-queue-worker.conf
   ```

2. مسیر پروژه را در فایل تنظیمات اصلاح کنید:
   ```bash
   sudo nano /etc/supervisor/conf.d/ronicrm-queue-worker.conf
   ```
   `/path/to/your/project` را با مسیر واقعی پروژه جایگزین کنید.

3. بارگذاری و راه‌اندازی:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start ronicrm-queue-worker:*
   ```

#### روش 2: استفاده از Cron Job (برای cPanel)

در cPanel به **"Cron Jobs"** بروید و این دو Cron Job را اضافه کنید:

**برای Scheduler (اجرای کمپین‌های زمان‌بندی شده):**
```
* * * * * cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1
```

**برای Queue Worker (توصیه نمی‌شود، اما اگر Supervisor ندارید):**
```
* * * * * cd /home/username/public_html && php artisan queue:work --tries=3 --timeout=90 --stop-when-empty >> /dev/null 2>&1
```

**نکته:** استفاده از Cron برای Queue Worker توصیه نمی‌شود چون ممکن است job های طولانی را قطع کند. بهتر است از Supervisor استفاده کنید.

برای اطلاعات بیشتر، `QUEUE_SETUP.md` را مطالعه کنید.

## مرحله 8: به‌روزرسانی پروژه (Deployment)

### 8.1. Pull کردن تغییرات جدید

هر زمان که تغییراتی در GitHub push کردید، در سرور:

```bash
cd ~/public_html  # یا مسیر پروژه
git pull origin main
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8.2. ایجاد Script برای Deployment (اختیاری)

می‌توانید یک فایل `deploy.sh` ایجاد کنید:

```bash
#!/bin/bash
cd /home/username/public_html
git pull origin main
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

سپس:

```bash
chmod +x deploy.sh
./deploy.sh
```

## عیب‌یابی

### مشکل: Permission Denied

```bash
chmod -R 755 storage bootstrap/cache
chown -R username:username storage bootstrap/cache
```

### مشکل: Composer یا npm پیدا نمی‌شود

مسیر کامل را استفاده کنید یا PATH را تنظیم کنید.

### مشکل: Queue کار نمی‌کند

مطمئن شوید که Cron Job برای Queue Worker تنظیم شده است.

### مشکل: 500 Error

1. لاگ‌ها را بررسی کنید: `storage/logs/laravel.log`
2. مجوزها را بررسی کنید
3. فایل `.env` را بررسی کنید
4. Cache را پاک کنید: `php artisan config:clear`

## نکات امنیتی

1. ✅ فایل `.env` هرگز نباید در Git باشد
2. ✅ `APP_DEBUG` را در production روی `false` قرار دهید
3. ✅ از HTTPS استفاده کنید
4. ✅ مجوزهای فایل را به درستی تنظیم کنید
5. ✅ از Strong Password برای دیتابیس استفاده کنید

## پشتیبانی

اگر مشکلی پیش آمد، لاگ‌ها را در `storage/logs/laravel.log` بررسی کنید.
