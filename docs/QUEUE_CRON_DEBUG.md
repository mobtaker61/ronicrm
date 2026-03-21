# راهنمای بررسی Queue و Cron

## رفع مشکل اتصال تلگرام در سرور

اگر وب‌هوک ربات تلگرام در سرور کار نمی‌کند:

1. **پاک کردن کش لاراول:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

2. **بررسی APP_URL در .env:** باید آدرس عمومی با `https://` باشد (مثلاً `https://crm.example.com`).

3. **اختیاری – TELEGRAM_WEBHOOK_URL:** اگر APP_URL با آدرس واقعی وب‌هوک فرق دارد، در .env اضافه کنید:
   ```
   TELEGRAM_WEBHOOK_URL=https://your-real-domain.com
   ```

4. **ثبت دستی وب‌هوک:** Settings → Telegram → دکمه «ثبت وب‌هوک همینک» را بزنید.

5. **برای اتصال اکانت کاربر (QR):** `TELEGRAM_API_ID` و `TELEGRAM_API_HASH` از my.telegram.org در .env قرار دهید.

---

## ساختار فعلی

### ۱. Cron Jobs مورد نیاز

| Cron | زمان | کار |
|------|------|-----|
| `schedule:run` | هر دقیقه | اجرای دستورات زمان‌بندی‌شده (campaigns:process هر دقیقه، telegram:fetch-incoming هر ۳ دقیقه) |
| `queue:work --stop-when-empty` | هر دقیقه | پردازش Jobهای صف و خروج بعد از اتمام |

### ۲. صف‌ها (Queues)

- **default**: Jobهای عمومی مثل TelegramSyncContactsJob، TelegramCrawlJob، TelegramSendToGroupsJob
- **campaigns**: Jobهای کمپین (از ProcessCampaigns)

### ۳. دستورات زمان‌بندی‌شده (Schedule)

- `campaigns:process` → هر دقیقه
- `telegram:fetch-incoming` → هر ۳ دقیقه (دریافت DM تلگرام وقتی daemon `telegram:listen-incoming` اجرا نمی‌شود)

---

## چگونه بررسی کنیم که کار می‌کند؟

### بررسی Cron

از cPanel یا SSH لاگ Cron را ببینید. معمولاً در `/var/log/cron` یا از cPanel → Cron Jobs → View Log.

### تست Scheduler

```bash
cd /home/roniplusae/crm
php artisan schedule:list
php artisan schedule:run -v
```

خروجی باید شامل `campaigns:process` و `telegram:fetch-incoming` باشد.

### بررسی صف Jobها

```bash
# پردازش یک Job (برای تست)
php artisan queue:work --once

# یا مستقیم از دیتابیس:
# SELECT id, queue, payload, attempts, created_at FROM jobs;
```

### بررسی Jobهای شکست‌خورده

```bash
php artisan queue:failed
```

برای retry:
```bash
php artisan queue:retry all
```

### اجرای دستی برای تست

```bash
# تست دریافت پیام تلگرام
php artisan telegram:fetch-incoming

# Sync مخاطبین تلگرام (زمانی که Queue کار نمی‌کند)
php artisan telegram:sync-contacts

# پردازش صف (یک Job)
php artisan queue:work --once
```

### وضعیت صف در رابط کاربری

در صفحه **Telegram Crawler** بخش Sync Contacts، وضعیت صف نمایش داده می‌شود:
- تعداد Jobهای در انتظار
- تعداد Jobهای شکست‌خورده

---

## خطای «start failed» یا «Failed to start» در MadelineProto

اگر sync یا fetch تلگرام با این خطا متوقف می‌شود، موارد احتمالی:

1. **قفل session**: یک عملیات دیگر روی session تلگرام در حال اجرا است
2. **Timeout**: اتصال به تلگرام بیش از حد مجاز طول کشید (الان ۱۸۰ ثانیه)
3. **محدودیت هاست یا فایروال**: دسترسی به سرورهای تلگرام مسدود است
4. **Session خراب**: فایل‌های session تلگرام corrupt شده‌اند

**راه‌حل‌ها:**
- Settings → Telegram → Reset Session (برای session خراب)
- صبر کنید؛ روی shared hosting اولین بار ممکن است ۲–۳ دقیقه طول بکشد
- از لاگ `storage/logs/madelineproto.log` و `storage/logs/laravel.log` جزئیات را ببینید

---

## خطای ۵۲۴ (Cloudflare Timeout)

وقتی «اجرای فوری» برای Sync Contacts روشن است، درخواست HTTP تا اتمام sync صبر می‌کند. Cloudflare معمولاً بعد از ۱۰۰ ثانیه قطع می‌کند → خطای ۵۲۴.

**راه‌حل**: گزینه «اجرای فوری» را خاموش کنید تا از Queue استفاده شود. با cron هر دقیقه، Job پردازش می‌شود.

---

## مسیر پروژه

در cron‌ها مسیر را چک کنید:
```
/home/roniplusae/crm
```
