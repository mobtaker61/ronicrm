# دریافت پیام‌های DM تلگرام در Inbox

## وضعیت فعلی
- **پیمایش گروه / اتصال کاربر**: MadelineProto (User API)
- **ارسال از Inbox** (وقتی `TelegramUserConnection` فعال است): MadelineProto
- **دریافت DM**: یا **Polling** (`telegram:fetch-incoming`) یا **Daemon** (`telegram:listen-incoming` + `IncomingMessageHandler`)
- **مخاطب تلگرام**: `customer_contacts` با `type=telegram` و `value=user_id`

وقتی به نویسنده‌ای از طریق پیمایش پیام می‌دهیم، پیام از **حساب کاربری** ما (User API) ارسال می‌شود. وقتی آن‌ها جواب می‌دهند، جواب به **حساب کاربری** ما می‌رسد، نه به ربات. بنابراین برای دریافت پاسخ آن‌ها باید از MadelineProto استفاده کنیم.

---

## دو روش پیشنهادی

### روش ۱: Job زمان‌بندی‌شده (Polling) — ساده‌تر

یک Job مثل `TelegramFetchIncomingJob` هر ۲ تا ۵ دقیقه اجرا شود و:

1. از MadelineProto متد `messages.getHistory` را برای هر گفتگوی خصوصی (کاربرانی که مخاطب داریم) صدا بزند
2. پیام‌های جدید با `direction=incoming` را در `telegram_messages` ذخیره کند
3. در صورت نبود customer، یک Customer جدید با contact تلگرام بسازد

**مزایا**: بدون daemon، فقط scheduler، هماهنگ با معماری Laravel  
**معایب**: تأخیر ۲–۵ دقیقه، مصرف بیشتر API در هر اجرا

---

### روش ۲: EventHandler و Daemon — زمانِ واقعی

یک دستور `php artisan telegram:listen-dms` که:

1. MadelineProto را با یک `SimpleEventHandler` اجرا کند
2. روی `Incoming & PrivateMessage` هندلر بگذارد
3. هر پیام ورودی را در دیتابیس ذخیره کند
4. با Supervisor یا systemd همیشه بالا نگه داشته شود

**مزایا**: دریافت لحظه‌ای، بدون polling  
**معایب**: نیاز به Supervisor و مدیریت سرویس دائمی

---

## پیشنهاد عملی

**مرحله ۱**: اجرای روش ۱ (Polling) — انجام شده:

1. متد `getPrivateChatHistory` و `getTelegramUserInfo` در `MadelineProtoService` اضافه شد
2. Job `TelegramFetchIncomingJob` ساخته شد که:
   - لیست گفتگوهای خصوصی (user) را از `getDialogs` می‌گیرد
   - برای هر گفتگو، تاریخچه را با `min_id` (آخرین پیام ذخیره‌شده) می‌گیرد
   - پیام‌های ورودی جدید را با `telegram_message_id` یکتا ذخیره می‌کند
   - اطلاعات کاربر (نام، فامیلی، تلفن، یوزرنیم) را از پاسخ API گرفته و در جدول `customers` و `customer_contacts` به‌روزرسانی می‌کند
3. زمان‌بندی در `bootstrap/app.php`: `telegram:fetch-incoming` با **`everyThreeMinutes`** (نیاز به `* * * * * php artisan schedule:run` در crontab سرور)

**مرحله ۲ (دریافت لحظه‌ای)**: `php artisan telegram:listen-incoming` با Supervisor. وقتی این daemon روشن است، **`telegram:fetch-incoming` به‌صورت خودکار از scheduler حذف می‌شود** (فایل `.madeline_listen_daemon_{id}` + PID زنده). دریافت فقط از `IncomingMessageHandler` → `TelegramSaveIncomingMessageJob`.

### چرا پیام ورودی نمی‌بینم؟

| علت | کار لازم |
|-----|----------|
| Cron اجرا نمی‌شود | هر دقیقه `php artisan schedule:run` (فقط در حالت **polling** لازم است). |
| فقط polling | تا ~۳ دقیقه تأخیر طبیعی است. |
| daemon روشن است اما هنوز چیزی نیست | در `laravel.log` دنبال `IncomingMessageHandler` و `TelegramSaveIncomingMessageJob: stored incoming` بگردید؛ اگر نیست، هندلر اصلاً رویداد را نمی‌گیرد (نسخه Madeline / نوع پیام). |
| می‌خواهید آنی باشد | Supervisor برای `telegram:listen-incoming`؛ polling دیگر لازم نیست. |
| `chat_id` خروجی `@username` و ورودی عددی | `resolved_chat_id` عددی ذخیره شود؛ رکوردهای قدیمی ممکن است دو نخ گفتگو بسازند. |

### مهم: یک session = یک مالک (وب **یا** daemon)

MadelineProto 8 با **کلاینت IPC کوتاه‌عمر** در PHP-FPM (بستن ناگهانی حلقهٔ رویداد) باعث خطاهایی مثل **`Channel was already closed`** و **تایم‌اوت ۳۰۰ ثانیه** روی `getAuthorization` می‌شود.

الگوی پایدار:

| حالت | Supervisor `telegram:listen-incoming` | ارسال اینباکس / کراول گروه / `fetch-incoming` |
|------|----------------------------------------|------------------------------------------------|
| **A — دریافت آنی** | روشن | خاموش (اپ عمداً خطا می‌دهد تا session نشکند) |
| **B — ارسال + polling** | خاموش | روشن (`schedule:run` + fetch هر ۳ دقیقه) |

- با **daemon**: scheduler دیگر `telegram:fetch-incoming` را اجرا نمی‌کند.
- اگر daemon روشن باشد، **`MadelineProtoService` قبل از هر عملیات خطا می‌دهد** — ابتدا Supervisor را متوقف کنید.
- برای بارگذاری کامل session (بدون IPC شکسته)، پیش‌فرض **`MADELINE_PROTO_FORCE_FULL=true`** در config است؛ با دامون همزمان نکنید.

### قفل MySQL (1205) هنگام باز کردن اینباکس

اگر هم‌زمان پیام ورودی در DB نوشته شود و همان لحظه صفحهٔ اینباکس `UPDATE read_at` بزند، ممکن است `Lock wait timeout` بگیرید. علامت «خوانده‌شده» در اپ **بعد از ارسال پاسخ HTTP** (`terminating`) اجرا می‌شود تا صفحه ۵۰۰ نشود. اگر `MADELINE_PROTO_CACHE_LOCK_BLOCK` کم باشد و ارسال Madeline طول بکشد، در `.env` مقدار را بزرگ‌تر کنید (مثلاً `420`).

---

## نکته درباره ارسال از Inbox

اگر **TelegramUserConnection** (Madeline) متصل باشد، ارسال از اینباکس با **User API** انجام می‌شود؛ در غیر این صورت ممکن است به Bot API برگردد (بسته به UI/تنظیمات).
