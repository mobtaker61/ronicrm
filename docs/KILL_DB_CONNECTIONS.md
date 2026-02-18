# بستن کانکشن‌های MySQL/MariaDB

هنگامی که خطای «Too many connections» رخ می‌دهد، باید کانکشن‌های Sleep را ببندید.

## روش ۱: از طریق WHM / ترمینال سرور (با دسترسی root)

### گزینه الف: کشتن کانکشن‌های Sleep یک دیتابیس

```bash
# با یوزر root مایاسکیول متصل شوید و دستورات زیر را اجرا کنید:
mysql -u root -p -e "
SELECT CONCAT('KILL ', id, ';') 
FROM information_schema.processlist 
WHERE user = 'roniplusae_crmdb' 
  AND db = 'roniplusae_crm' 
  AND command = 'Sleep'
  AND id != CONNECTION_ID();
"
```

خروجی این دستور لیستی از `KILL 12345;` خواهد بود. خروجی را کپی کرده و دوباره در mysql اجرا کنید.

### گزینه ب: یک خطی (همه کانکشن‌های Sleep را kill می‌کند)

```bash
mysql -u root -p -N -e "
SELECT CONCAT('KILL ', id, ';') 
FROM information_schema.processlist 
WHERE user = 'roniplusae_crmdb' AND command = 'Sleep' AND id != CONNECTION_ID();
" | mysql -u root -p
```

### گزینه ج: ریستارت سرویس MySQL/MariaDB

از WHM: **Restart Services** → **MySQL** یا **MariaDB**

⚠️ این کار همه کانکشن‌های همه وب‌سایت‌ها را می‌بندد. در shared hosting ممکن است چند ثانیه downtime ایجاد شود.

## روش ۲: از طریق phpMyAdmin (اگر دسترسی دارید)

دوستی که دسترسی دارد می‌تواند در تب **Processes** کانکشن‌های Sleep را انتخاب و Kill کند.

---

## جلوگیری از تکرار

۱. **استفاده از درایور MariaDB:** در `.env` مقدار زیر را قرار دهید:
   ```
   DB_CONNECTION=mariadb
   ```

۲. **کاهش PHP-FPM workers:** در shared hosting معمولاً از کنترل پنل قابل تنظیم است. تعداد workerها را کم کنید.

۳. **کاهش Queue workers:** اگر از queue استفاده می‌کنید، تعداد workerها را کم کنید (مثلاً 1 تا 2).

۴. **max_connections در MySQL:** از مدیر سرور بخواهید در صورت امکان `max_connections` را افزایش دهد (پیش‌فرض ~۱۵۱ است).
