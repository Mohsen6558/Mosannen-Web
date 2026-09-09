# مسنن — سامانه مدیریت کلینیک دندانپزشکی

بازنویسی تحت وب نرم‌افزار دسکتاپ **Mosanen** (VB.NET / WinForms) با
Laravel، Inertia، Vue 3 و PostgreSQL.

---

## راه‌اندازی سریع

```bash
cp .env.example .env

# ایمیج اپ با کاربر www-data (uid 33) اجرا می‌شود؛ دو پوشه‌ای که در آن‌ها
# می‌نویسد باید مال او باشند:
sudo chown -R 33:33 storage bootstrap/cache

docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

# ساخت فایل‌های فرانت (یک بار)
docker compose --profile dev run --rm vite npm install
docker compose --profile dev run --rm vite npm run build
```

سپس <http://localhost:8080> — نام کاربری `admin` و رمز `password`
(در اولین ورود باید تغییر کند).

> اگر روی سرور از قبل PostgreSQL دارید، پورت ۵۴۳۲ اشغال است؛
> `POSTGRES_PORT` را در `.env` عوض کنید. این پورت فقط روی `127.0.0.1`
> باز می‌شود و از شبکه در دسترس نیست.

یا با `make`:

```bash
make install     # نصب کامل
make up          # اجرا
make logs        # مشاهده لاگ‌ها
make test        # اجرای تست‌ها
make import-dry  # تمرین مهاجرت داده بدون نوشتن
```

---

## معماری

| لایه | فناوری |
|---|---|
| بک‌اند | Laravel 13 (PHP 8.4) |
| فرانت‌اند | Vue 3 + Inertia + Tailwind 4، تمام‌RTL |
| دیتابیس | PostgreSQL 16 (کولیشن `fa-IR`، افزونه‌های `pg_trgm` و `unaccent`) |
| صف و کش | Redis |
| فایل‌ها | دیسک محلی یا S3/MinIO — با یک متغیر محیطی جابه‌جا می‌شود |
| استقرار | Docker، فقط با ایمیج‌های آماده — بدون Dockerfile سفارشی |

### سرویس‌های Docker

| سرویس | ایمیج | نقش |
|---|---|---|
| `app` | `serversideup/php:8.4-fpm-nginx` | nginx + php-fpm |
| `queue` | همان | کارگر صف (پیامک، بندانگشتی) |
| `scheduler` | همان | پشتیبان‌گیری شبانه، یادآوری نوبت |
| `postgres` | `postgres:16-alpine` | دیتابیس |
| `redis` | `redis:7-alpine` | کش و صف |
| `minio` | `minio/minio` | اختیاری — پروفایل `s3` |
| `vite` | `node:22-alpine` | اختیاری — پروفایل `dev` |

```bash
docker compose --profile s3 up -d      # با MinIO
docker compose --profile dev up -d     # با hot reload
```

فقط پورت وب (`APP_PORT`) روی همه‌ی رابط‌ها باز است. PostgreSQL و MinIO
به `127.0.0.1` محدود شده‌اند تا از شبکه‌ی کلینیک قابل دسترسی نباشند.

nginx داخل کانتینر فقط روی IPv4 گوش می‌دهد
(`docker/nginx/http.conf.template`). داکر پورت را روی IPv4 منتشر می‌کند،
پس چیزی از دست نمی‌رود — و روی میزبانی که IPv6 در کرنلش کامپایل نشده،
کانتینر دیگر در حلقه‌ی ری‌استارت نمی‌افتد.

مهاجرت‌ها **به‌صورت خودکار هنگام بالا آمدن کانتینر اجرا نمی‌شوند**؛ اجرای
ناخواسته‌ی migration روی داده‌ی بیماران چیزی نیست که بعداً بفهمید.

---

## ماژول‌ها

پرونده بیماران · درمان با نمودار دندان · مالی و تخفیف · بیمه · عکس‌برداری ·
نسخه و تجویز دارو · انبار · پیامک · گزارش‌ها · نوبت‌دهی¹ · کاربران و
سطوح دسترسی · گزارش فعالیت‌ها¹

<sub>¹ در نسخه‌ی قدیمی وجود نداشت.</sub>

---

## مهاجرت داده از سیستم قدیمی

```bash
# ۱) اتصال به SQL Server قدیمی را در .env تنظیم کنید
LEGACY_DB_HOST=192.168.1.10
LEGACY_DB_DATABASE=azarbazak
LEGACY_DB_USERNAME=sa
LEGACY_DB_PASSWORD=...
LEGACY_IMAGES_PATH=/legacy-images   # مسیر mount شده‌ی \\server\AppIMG\

# ۲) تمرین بدون نوشتن — گزارش کامل می‌دهد
php artisan legacy:import --dry-run

# ۳) اجرای واقعی
php artisan legacy:import
```

**نکته:** افزونه‌ی `pdo_sqlsrv` باید روی PHP نصب باشد.

### ویژگی‌های مهم فرمان

- **بی‌خطر در اجرای مکرر** — هر رکورد کلید اصلی قدیمی‌اش را در `legacy_id`
  نگه می‌دارد، پس اجرای دوم به‌جای تکرار، به‌روزرسانی می‌کند. یعنی می‌توانید
  یک بار تمرینی و یک بار نهایی (delta) در لحظه‌ی سوییچ اجرا کنید.
- **تراکنش واحد** — خطا در وسط کار، نیمی از بیماران را جا نمی‌گذارد.
- **رکورد خراب، کل کار را متوقف نمی‌کند** — شمرده و در گزارش با دلیل
  گزارش می‌شود.
- `--only=patients,treatments` برای اجرای مرحله‌ای.

### تبدیل‌هایی که انجام می‌شود

| در سیستم قدیمی | در نسخه‌ی وب |
|---|---|
| رمز عبور **متن ساده** در `tblUser` | وارد **نمی‌شود**؛ رمز تصادفی + اجبار به تغییر در اولین ورود |
| دسترسی به‌صورت رشته‌ی `"1-2-7-8"` | ۳۲ دسترسی نام‌دار در ۱۱ ماژول و ۵ نقش |
| تاریخ شمسی به‌صورت **رشته** (`'1403/05/12'`) | ستون `date` میلادی؛ تبدیل فقط در لایه‌ی نمایش |
| `ToothName` = `"_3UR,_EUL,_6LL"` | جدول رابطه‌ای با نُماد **FDI** (`13`، `65`، `36`) |
| مبلغ بدون نوع مشخص | `bigint` ریال — هرگز اعشاری |
| بدون کلید خارجی، بدون لاگ | کلید خارجی کامل + جدول `activity_logs` |
| غلط املایی ستون‌ها (`Birhdate`, `Permession`, `Opration`) | نام‌گذاری تمیز `snake_case` |
| `dbo.getReminderMoney` (UDF) | `Patient::balance` و گزارش بدهکاران |

تصاویر رادیوگرافی تنها وقتی کپی می‌شوند که مسیر `LEGACY_IMAGES_PATH` در
دسترس باشد؛ در غیر این صورت رکورد و ارتباط دندان‌ها وارد می‌شود و فایل را
می‌توان بعداً پیوست کرد.

---

## تصاویر رادیوگرافی

نسخه‌ی قدیمی تصاویر را از شیر شبکه‌ی `\\server\AppIMG\` می‌خواند و منبعشان
**DBSWIN** (نرم‌افزار ویندوزی Dürr Dental) بود. یک وب‌اپ ابری به هیچ‌کدام
دسترسی ندارد. سه گزینه:

1. **سرور داخل کلینیک** (پیشنهادی برای شروع) — همین Docker روی یک مینی‌سرور
   در مطب؛ تصاویر روی volume محلی.
2. **پل محلی** — یک سرویس کوچک ویندوزی که پوشه‌ی DBSWIN را watch کند و
   فایل جدید را به API آپلود کند.
3. **آپلود دستی** از خود برنامه.

در هر سه حالت کد یکسان است: از `Storage` با درایور قابل تعویض استفاده
می‌شود. فایل‌ها **هیچ‌وقت public نیستند** — با نام تصادفی ذخیره و از طریق
کنترلر با بررسی دسترسی stream می‌شوند.

---

## پیامک

```env
SMS_DRIVER=log        # log | kavenegar | smsir
KAVENEGAR_API_KEY=...
SMS_SENDER=...
```

پیام‌ها **اول ذخیره و بعد از صف ارسال** می‌شوند، تا قطعی سرویس‌دهنده
کاربری را که مقابل بیمار ایستاده معطل نکند. متن قالب‌ها از داخل برنامه
قابل ویرایش است.

---

## پشتیبان‌گیری

```bash
php artisan clinic:backup            # دستی
php artisan clinic:backup --keep=30  # با تعداد نگهداری دلخواه
```

سرویس `scheduler` هر شب ساعت ۲:۳۰ اجرا می‌کند و ۱۴ نسخه‌ی آخر را نگه
می‌دارد. خروجی در `storage/app/backups`.

---

## توسعه

```bash
composer install && npm install
php artisan migrate --seed     # داده‌ی نمونه فقط در local
npm run dev
php artisan serve
```

کاربران نمونه: `admin` / `doctor` / `reception` — همه با رمز `password`.

```bash
php artisan test          # ۴۱ تست
./vendor/bin/pint         # فرمت PHP
```

تست‌ها روی PostgreSQL اجرا می‌شوند (نه sqlite)، چون کوئری‌های جستجو از
`ILIKE` و ایندکس trigram استفاده می‌کنند. دیتابیس `mosannen_test` باید
وجود داشته باشد.
