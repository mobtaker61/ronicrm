# اتصال تلگرام در محیط لوکال (Localhost)

اگر در لوکال نمی‌توانید QR Code تلگرام را دریافت کرده یا به تلگرام متصل شوید، احتمالاً به دلیل محدودیت‌های تلگرام یا مرورگر (HTTPS) است. استفاده از **ngrok** این مشکل را حل می‌کند.

## روش ۱: استفاده از ngrok (پیشنهادی)

ngrok یک تونل امن HTTPS به لوکال شما ایجاد می‌کند.

### ۱. نصب ngrok

- **Windows**: از [ngrok.com](https://ngrok.com/download) دانلود کنید یا `choco install ngrok`
- **Mac**: `brew install ngrok`
- **Linux**: `snap install ngrok` یا دانلود از سایت

### ۲. ثبت‌نام و احراز هویت

1. در [ngrok.com](https://ngrok.com) ثبت‌نام کنید (رایگان)
2. Auth token خود را از داشبورد کپی کنید
3. اجرا کنید: `ngrok config add-authtoken YOUR_TOKEN`

### ۳. اجرای تونل

پورت Laravel خود را (مثلاً 8000) expose کنید:

```bash
ngrok http 8000
```

خروجی شبیه این خواهد بود:

```
Forwarding   https://abc123.ngrok-free.app -> http://localhost:8000
```

### ۴. تنظیم APP_URL

در `.env` لوکال:

```env
APP_URL=https://abc123.ngrok-free.app
```

سپس cache را پاک کنید:

```bash
php artisan config:clear
```

### ۵. دسترسی به برنامه

به جای `http://localhost:8000`، از آدرس ngrok استفاده کنید:

```
https://abc123.ngrok-free.app
```

صفحه Settings → Telegram را باز کنید و «Connect via QR Code» را بزنید. QR باید نمایش داده شود و پس از اسکن، اتصال برقرار می‌شود.

### نکته مهم

آدرس ngrok در هر بار اجرا تغییر می‌کند (مگر نسخه پولی). پس از هر restart باید `APP_URL` و cache را مجدداً تنظیم کنید.

---

## روش ۲: اتصال در Production و تست Job در لوکال

اگر نمی‌خواهید در لوکال به تلگرام متصل شوید:

1. در **سرور Production** (مثلاً roniplus.ae) به تلگرام متصل شوید (QR را آنجا اسکن کنید)
2. فایل‌های session را از سرور کپی کنید:
   - پوشه `storage/app/telegram-user-sessions/` را دانلود کنید
3. این پوشه را در پروژه لوکال خود قرار دهید

**هشدار**: فایل session حاوی اطلاعات احراز هویت است. آن را به اشتراک نگذارید یا در مخزن قرار ندهید.

---

## خطای Lightstate

اگر خطای `Could not read the lightstate file` دریافت کردید:

1. به **Settings → Telegram** بروید
2. دکمه **Reset Session** را بزنید
3. دوباره **Connect via QR Code** را بزنید و QR را اسکن کنید

این کار فایل‌های session خراب را پاک کرده و اتصال جدید ایجاد می‌کند.
