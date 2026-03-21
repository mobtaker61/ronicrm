# دریافت پیام‌های DM تلگرام در Inbox

## وضعیت فعلی
- **پیمایش گروه**: با MadelineProto (User API) انجام می‌شود
- **ارسال به نویسندگان**: با MadelineProto (User API)
- **ارسال از Inbox**: با TelegramService (Bot API)
- **نویسندگان ثبت‌شده**: در مخاطبین با `type=telegram` و `value=user_id`

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

**مرحله ۲ (اختیاری — دریافت لحظه‌ای)**: دستور `php artisan telegram:listen-incoming` با Supervisor/systemd همیشه روشن نگه دارید. **همزمان** با job polling اجرا نکنید (یک session MadelineProto).

### چرا پیام ورودی نمی‌بینم؟

| علت | کار لازم |
|-----|----------|
| Cron اجرا نمی‌شود | روی سرور حتماً هر دقیقه `schedule:run` را اجرا کنید. |
| فقط polling دارید | تا ۳ دقیقه تأخیر طبیعی است؛ قبلاً با `hourly` ممکن بود ساعت‌ها طول بکشد. |
| می‌خواهید آنی باشد | `telegram:listen-incoming` را به‌صورت daemon اجرا کنید و polling را غیرفعال کنید. |
| `chat_id` خروجی `@username` بوده و ورودی عددی است | با اصلاح اخیر، بعد از ارسال باید `resolved_chat_id` عددی ذخیره شود؛ برای رکوردهای قدیمی ممکن است دو رشتهٔ جدا در اینباکس ببینید. |

---

## نکته درباره ارسال از Inbox

الان ارسال از Inbox با **Bot API** انجام می‌شود. کاربرانی که فقط از پیمایش مخاطب شده‌اند ممکن است ربات را استارت نکرده باشند و Bot نتواند به آن‌ها پیام بفرستد.

برای این مخاطبین باید:
- اگر `TelegramUserConnection` فعال است، برای ارسال از **MadelineProto** استفاده شود (مثل ارسال در پیمایش)
- در غیر این صورت همان Bot API باقی بماند

این باعث می‌شود ارسال به نویسندگان پیمایش‌شده همیشه با User API انجام شود و با دریافت از همان اکانت، جریان پیام درست باشد.
