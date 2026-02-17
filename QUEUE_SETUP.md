# راهنمای تنظیم Queue Worker برای Production

این راهنما برای اطمینان از اجرای صحیح Queue Worker و Scheduler در سرور production است.

## 📋 فهرست مطالب

1. [تنظیمات .env](#تنظیمات-env)
2. [تنظیم Supervisor (برای Linux)](#تنظیم-supervisor-برای-linux)
3. [تنظیم Systemd (برای Linux)](#تنظیم-systemd-برای-linux)
4. [تنظیم Cron Job (برای Scheduler)](#تنظیم-cron-job-برای-scheduler)
5. [بررسی و Monitoring](#بررسی-و-monitoring)
6. [Troubleshooting](#troubleshooting)

---

## تنظیمات .env

در فایل `.env` سرور، این تنظیمات را بررسی کنید:

```env
# Queue Configuration
QUEUE_CONNECTION=database

# Application URL (برای دسترسی به فایل‌های آپلود شده)
APP_URL=https://yourdomain.com

# Environment
APP_ENV=production
APP_DEBUG=false
```

**مهم:** `QUEUE_CONNECTION` باید `database` باشد (نه `sync`).

---

## تنظیم Supervisor (برای Linux)

Supervisor بهترین راه برای اجرای دائمی Queue Worker است.

### 1. نصب Supervisor

```bash
sudo apt-get update
sudo apt-get install supervisor
```

### 2. ایجاد فایل تنظیمات

فایل `/etc/supervisor/conf.d/ronicrm-queue-worker.conf` را ایجاد کنید:

```ini
[program:ronicrm-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=default,campaigns
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/queue-worker.log
stopwaitsecs=3600
```

**توجه:** 
- `/path/to/your/project` را با مسیر واقعی پروژه جایگزین کنید
- `user=www-data` را با کاربر وب سرور خود (مثلاً `nginx` یا `apache`) جایگزین کنید
- `numprocs=2` تعداد worker های موازی است (می‌توانید تغییر دهید)

### 3. بارگذاری تنظیمات

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ronicrm-queue-worker:*
```

### 4. بررسی وضعیت

```bash
sudo supervisorctl status
```

باید خروجی مشابه این را ببینید:

```
ronicrm-queue-worker:ronicrm-queue-worker_00   RUNNING   pid 12345, uptime 0:05:23
ronicrm-queue-worker:ronicrm-queue-worker_01   RUNNING   pid 12346, uptime 0:05:23
```

### 5. دستورات مفید

```bash
# راه‌اندازی مجدد
sudo supervisorctl restart ronicrm-queue-worker:*

# توقف
sudo supervisorctl stop ronicrm-queue-worker:*

# مشاهده لاگ
tail -f /path/to/your/project/storage/logs/queue-worker.log
```

---

## تنظیم Systemd (برای Linux - جایگزین Supervisor)

اگر Supervisor ندارید، می‌توانید از Systemd استفاده کنید.

### 1. ایجاد Service File

فایل `/etc/systemd/system/ronicrm-queue-worker.service` را ایجاد کنید:

```ini
[Unit]
Description=RoniCRM Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=campaigns,default

[Install]
WantedBy=multi-user.target
```

### 2. فعال‌سازی و راه‌اندازی

```bash
sudo systemctl daemon-reload
sudo systemctl enable ronicrm-queue-worker
sudo systemctl start ronicrm-queue-worker
```

### 3. بررسی وضعیت

```bash
sudo systemctl status ronicrm-queue-worker
```

---

## تنظیم Cron Job (برای Scheduler)

Laravel Scheduler برای پردازش کمپین‌های زمان‌بندی شده استفاده می‌شود.

### 1. باز کردن Crontab

```bash
crontab -e
```

### 2. اضافه کردن خط زیر

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**مهم:** `/path/to/your/project` را با مسیر واقعی پروژه جایگزین کنید.

### 3. بررسی Cron Job

```bash
crontab -l
```

### 4. تست Scheduler

```bash
php artisan schedule:run
```

باید خروجی مشابه این را ببینید:

```
Running scheduled command: campaigns:process
```

---

## بررسی و Monitoring

### 1. بررسی Queue Jobs

```bash
# مشاهده تعداد job های در صف
php artisan queue:monitor

# مشاهده failed jobs
php artisan queue:failed
```

### 2. بررسی لاگ‌ها

```bash
# لاگ Queue Worker
tail -f storage/logs/queue-worker.log

# لاگ Laravel
tail -f storage/logs/laravel.log

# لاگ Supervisor
sudo tail -f /var/log/supervisor/supervisord.log
```

### 3. بررسی وضعیت Database Jobs

می‌توانید مستقیماً در دیتابیس بررسی کنید:

```sql
-- مشاهده job های در صف
SELECT * FROM jobs;

-- مشاهده failed jobs
SELECT * FROM failed_jobs;

-- مشاهده تعداد job ها
SELECT COUNT(*) FROM jobs;
```

### 4. ایجاد یک صفحه Monitoring (اختیاری)

می‌توانید یک route برای بررسی وضعیت اضافه کنید:

```php
// routes/web.php
Route::get('/admin/queue-status', function () {
    return [
        'jobs_pending' => DB::table('jobs')->count(),
        'jobs_failed' => DB::table('failed_jobs')->count(),
        'queue_connection' => config('queue.default'),
    ];
})->middleware('auth')->middleware('role:admin');
```

---

## Troubleshooting

### مشکل 1: Job ها اجرا نمی‌شوند

**بررسی:**
1. آیا Queue Worker در حال اجرا است؟
   ```bash
   sudo supervisorctl status
   # یا
   sudo systemctl status ronicrm-queue-worker
   ```

2. آیا `QUEUE_CONNECTION=database` در `.env` است؟
   ```bash
   grep QUEUE_CONNECTION .env
   ```

3. آیا جدول `jobs` وجود دارد؟
   ```bash
   php artisan migrate:status
   ```

**راه حل:**
```bash
# راه‌اندازی مجدد Queue Worker
sudo supervisorctl restart ronicrm-queue-worker:*

# یا
sudo systemctl restart ronicrm-queue-worker

# پاک کردن cache
php artisan config:clear
php artisan cache:clear
```

### مشکل 2: Failed Jobs زیاد می‌شوند

**بررسی:**
```bash
php artisan queue:failed
```

**راه حل:**
```bash
# Retry failed jobs
php artisan queue:retry all

# یا retry یک job خاص
php artisan queue:retry {job-id}

# پاک کردن failed jobs
php artisan queue:flush
```

### مشکل 3: Scheduler اجرا نمی‌شود

**بررسی:**
1. آیا Cron Job تنظیم شده است؟
   ```bash
   crontab -l
   ```

2. آیا مسیر پروژه درست است؟
   ```bash
   cd /path/to/your/project && php artisan schedule:run
   ```

**راه حل:**
```bash
# تست scheduler
php artisan schedule:list

# اجرای دستی
php artisan schedule:run -v
```

### مشکل 4: Queue Worker بعد از restart سرور اجرا نمی‌شود

**راه حل:**
مطمئن شوید که Supervisor یا Systemd service در حالت `autostart` است:

```bash
# برای Supervisor
sudo supervisorctl status

# برای Systemd
sudo systemctl is-enabled ronicrm-queue-worker
```

اگر `disabled` است:
```bash
sudo systemctl enable ronicrm-queue-worker
```

---

## دستورات مفید

```bash
# مشاهده وضعیت Queue
php artisan queue:work --help

# اجرای دستی Queue Worker (برای تست)
php artisan queue:work --queue=campaigns,default --tries=3

# پاک کردن تمام job های در صف (احتیاط!)
php artisan queue:clear

# مشاهده failed jobs
php artisan queue:failed

# Retry همه failed jobs
php artisan queue:retry all

# مشاهده لیست scheduled commands
php artisan schedule:list

# تست scheduler
php artisan schedule:run -v
```

---

## نکات مهم

1. **همیشه از Supervisor یا Systemd استفاده کنید** - اجرای دستی `queue:work` برای production مناسب نیست.

2. **تنظیم `--tries` و `--timeout`** - برای جلوگیری از job های بی‌نهایت.

3. **Monitoring** - لاگ‌ها را به طور منظم بررسی کنید.

4. **Backup** - قبل از تغییرات مهم، از دیتابیس backup بگیرید.

5. **Testing** - بعد از تنظیمات، حتماً تست کنید:
   - یک کمپین draft ایجاد کنید
   - آن را start کنید
   - بررسی کنید که job ها اجرا می‌شوند
   - بررسی کنید که پیام‌ها ارسال می‌شوند

---

## چک‌لیست نهایی

- [ ] `QUEUE_CONNECTION=database` در `.env` تنظیم شده
- [ ] Supervisor یا Systemd service ایجاد و فعال شده
- [ ] Queue Worker در حال اجرا است
- [ ] Cron Job برای Scheduler تنظیم شده
- [ ] Scheduler به درستی اجرا می‌شود
- [ ] لاگ‌ها بررسی شده‌اند
- [ ] تست کامل انجام شده

---

## پشتیبانی

اگر مشکلی پیش آمد:
1. لاگ‌ها را بررسی کنید
2. وضعیت Queue Worker را بررسی کنید
3. وضعیت Scheduler را بررسی کنید
4. دیتابیس jobs را بررسی کنید
