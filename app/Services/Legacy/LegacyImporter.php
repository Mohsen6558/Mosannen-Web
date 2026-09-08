<?php

namespace App\Services\Legacy;

use App\Models\Drug;
use App\Models\DrugVariant;
use App\Models\Insurance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Radiograph;
use App\Models\SmsMessage;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentService;
use App\Models\User;
use App\Support\JalaliDate;
use App\Support\Permissions;
use App\Support\Teeth;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads the legacy VB.NET application's SQL Server database and writes the
 * cleaned equivalent into PostgreSQL.
 *
 * Design rules:
 *  - The legacy connection is opened read-only. Nothing is ever written back.
 *  - Every row carries its source primary key in `legacy_id`, so the whole
 *    import is idempotent: re-running updates in place instead of duplicating.
 *    That makes a rehearsal run, then a final delta run at cutover, safe.
 *  - A row that cannot be converted is skipped and counted, never fatal. A
 *    ten-year-old database always contains a few unparseable dates.
 */
class LegacyImporter
{
    private ImportReport $report;

    /** Maps legacy primary keys to new ones, per table, for FK rewriting. */
    private array $map = [];

    public function __construct(
        private readonly bool $dryRun = false,
        private readonly ?Closure $progress = null,
    ) {
        $this->report = new ImportReport;
    }

    public function report(): ImportReport
    {
        return $this->report;
    }

    private function legacy()
    {
        return DB::connection('legacy');
    }

    private function say(string $message): void
    {
        if ($this->progress) {
            ($this->progress)($message);
        }
    }

    /** Run every step in dependency order. */
    public function run(array $only = []): void
    {
        $steps = [
            'insurances' => fn () => $this->importInsurances(),
            'payment_types' => fn () => $this->importPaymentTypes(),
            'treatment_catalog' => fn () => $this->importTreatmentCatalog(),
            'drugs' => fn () => $this->importDrugs(),
            'stock_items' => fn () => $this->importStockItems(),
            'users' => fn () => $this->importUsers(),
            'patients' => fn () => $this->importPatients(),
            'treatments' => fn () => $this->importTreatments(),
            'payments' => fn () => $this->importPayments(),
            'stock_movements' => fn () => $this->importStockMovements(),
            'radiographs' => fn () => $this->importRadiographs(),
            'sms' => fn () => $this->importSms(),
        ];

        foreach ($steps as $name => $step) {
            if ($only && ! in_array($name, $only, true)) {
                continue;
            }

            $this->say("→ {$name}");
            $step();
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Stream a legacy table in chunks so memory stays flat on large tables. */
    private function each(string $table, Closure $handler, string $orderBy = 'ID'): void
    {
        $this->legacy()->table($table)->orderBy($orderBy)->chunk(500, function ($rows) use ($handler) {
            foreach ($rows as $row) {
                $handler((array) $row);
            }
        });
    }

    /** Case-insensitive column read; legacy casing is inconsistent. */
    private function col(array $row, string ...$names): mixed
    {
        $lower = array_change_key_case($row, CASE_LOWER);

        foreach ($names as $name) {
            $key = strtolower($name);
            if (array_key_exists($key, $lower) && $lower[$key] !== null && $lower[$key] !== '') {
                return $lower[$key];
            }
        }

        return null;
    }

    private function str(array $row, string ...$names): ?string
    {
        $v = $this->col($row, ...$names);

        return $v === null ? null : (trim((string) $v) ?: null);
    }

    private function int(array $row, string ...$names): int
    {
        return (int) ($this->col($row, ...$names) ?? 0);
    }

    /** Money in the legacy DB is a whole number of Rial, sometimes as text. */
    private function money(array $row, string ...$names): int
    {
        $raw = JalaliDate::toEnglishDigits((string) ($this->col($row, ...$names) ?? '0'));

        return (int) round((float) preg_replace('/[^\d.\-]/', '', $raw));
    }

    private function date(array $row, string ...$names): ?string
    {
        return JalaliDate::parse($this->str($row, ...$names))?->toDateString();
    }

    /** Persist unless this is a dry run; always record the id mapping. */
    private function store(string $bucket, int $legacyId, string $model, array $attributes): void
    {
        if ($this->dryRun) {
            $this->report->imported($bucket);

            return;
        }

        /** @var Model $model */
        $existing = $model::query()->where('legacy_id', $legacyId)->first();

        if ($existing) {
            $existing->fill($attributes)->save();
            $this->report->updated($bucket);
            $this->map[$bucket][$legacyId] = $existing->getKey();

            return;
        }

        $created = $model::query()->create($attributes + ['legacy_id' => $legacyId]);
        $this->report->imported($bucket);
        $this->map[$bucket][$legacyId] = $created->getKey();
    }

    // ── Reference data ───────────────────────────────────────────────────

    private function importInsurances(): void
    {
        $this->each('tblBimeh', function (array $row) {
            $this->report->read('insurances');
            $name = $this->str($row, 'Name');

            if (! $name) {
                $this->report->skipped('insurances', 'نام خالی');

                return;
            }

            $this->store('insurances', $this->int($row, 'ID'), Insurance::class, [
                'name' => $name,
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });
    }

    private function importPaymentTypes(): void
    {
        $this->each('tblPayType', function (array $row) {
            $this->report->read('payment_types');
            $name = $this->str($row, 'Name');

            if (! $name) {
                $this->report->skipped('payment_types', 'نام خالی');

                return;
            }

            $this->store('payment_types', $this->int($row, 'ID'), PaymentType::class, [
                'name' => $name,
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });
    }

    /** tblTitle → categories, tblSubtitles → billable services with tariffs. */
    private function importTreatmentCatalog(): void
    {
        $this->each('tblTitle', function (array $row) {
            $this->report->read('treatment_catalog');
            $name = $this->str($row, 'Name');

            if (! $name) {
                $this->report->skipped('treatment_catalog', 'گروه درمان بدون نام');

                return;
            }

            $this->store('treatment_categories', $this->int($row, 'ID'), TreatmentCategory::class, [
                'name' => $name,
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });

        $this->each('tblSubtitles', function (array $row) {
            $this->report->read('treatment_catalog');
            $name = $this->str($row, 'Name');
            $categoryId = $this->ref('treatment_categories', $this->col($row, 'Parent'));

            if (! $name || ! $categoryId) {
                $this->report->skipped('treatment_catalog', 'زیردرمان بدون نام یا بدون گروه معتبر');

                return;
            }

            $this->store('treatment_services', $this->int($row, 'ID'), TreatmentService::class, [
                'treatment_category_id' => $categoryId,
                'name' => $name,
                'price' => max(0, $this->money($row, 'Money')),
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });
    }

    /** tblDrag → drugs, tblSubDrag → prescribable variants. */
    private function importDrugs(): void
    {
        $this->each('tblDrag', function (array $row) {
            $this->report->read('drugs');
            $name = $this->str($row, 'Name');

            if (! $name) {
                $this->report->skipped('drugs', 'دارو بدون نام');

                return;
            }

            $this->store('drugs', $this->int($row, 'ID'), Drug::class, [
                'name' => $name,
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });

        $this->each('tblSubDrag', function (array $row) {
            $this->report->read('drugs');
            $name = $this->str($row, 'Name');
            $drugId = $this->ref('drugs', $this->col($row, 'Parent'));

            if (! $name || ! $drugId) {
                $this->report->skipped('drugs', 'زیردارو بدون نام یا بدون دارو معتبر');

                return;
            }

            $this->store('drug_variants', $this->int($row, 'ID'), DrugVariant::class, [
                'drug_id' => $drugId,
                'name' => $name,
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });
    }

    private function importStockItems(): void
    {
        $this->each('tblStore', function (array $row) {
            $this->report->read('stock_items');
            $name = $this->str($row, 'Name');

            if (! $name) {
                $this->report->skipped('stock_items', 'کالا بدون نام');

                return;
            }

            $this->store('stock_items', $this->int($row, 'ID'), StockItem::class, [
                'name' => $name,
                'item_order' => $this->int($row, 'ItemOrder'),
                'is_active' => true,
            ]);
        });
    }

    // ── Users ────────────────────────────────────────────────────────────

    /**
     * Legacy passwords were stored in plaintext and compared with a literal
     * SQL equality check. They are deliberately NOT imported: each account
     * gets a random password and `must_change_password`, so the first login
     * forces a reset. The old dash-separated permission flags are expanded
     * into named permissions via Permissions::legacyMap().
     */
    private function importUsers(): void
    {
        $this->each('tblUser', function (array $row) {
            $this->report->read('users');

            $username = $this->str($row, 'Name');

            if (! $username) {
                $this->report->skipped('users', 'کاربر بدون نام کاربری');

                return;
            }

            $legacyId = $this->int($row, 'ID');
            $fullName = $this->str($row, 'NameFamily') ?? $username;

            if ($this->dryRun) {
                $this->report->imported('users');

                return;
            }

            $user = User::query()->where('legacy_id', $legacyId)->first();
            $isNew = ! $user;

            if ($isNew) {
                $user = new User(['legacy_id' => $legacyId]);
                $user->legacy_id = $legacyId;
                // Random, unknown to anyone — the account must go through reset.
                $user->password = Hash::make(Str::random(40));
                $user->must_change_password = true;
            }

            $user->username = Str::lower($username);
            $user->name = $fullName;
            $user->full_name = $fullName;
            $user->is_active = true;
            $user->save();

            $this->map['users'][$legacyId] = $user->id;
            $isNew ? $this->report->imported('users') : $this->report->updated('users');

            $this->applyLegacyPermissions($user, $this->str($row, 'Permession'));
        });
    }

    private function applyLegacyPermissions(User $user, ?string $flags): void
    {
        $granted = Permissions::legacyBaseline();
        $map = Permissions::legacyMap();

        foreach (preg_split('/[-,\s]+/', (string) $flags, -1, PREG_SPLIT_NO_EMPTY) as $flag) {
            $granted = [...$granted, ...($map[$flag] ?? [])];
        }

        $granted = array_values(array_unique($granted));

        // Anyone who held the old "اطلاعات اولیه" flag ran the whole system:
        // that flag also unlocked user management and database restore.
        if (in_array('users.manage', $granted, true)) {
            $user->syncRoles(['admin']);
            $user->syncPermissions([]);

            return;
        }

        $user->syncRoles([]);
        $user->syncPermissions($granted);
    }

    // ── Clinical data ────────────────────────────────────────────────────

    private function importPatients(): void
    {
        $this->each('tblCustomer', function (array $row) {
            $this->report->read('patients');

            $legacyId = $this->int($row, 'ID');
            $first = $this->str($row, 'Name') ?? '';
            $last = $this->str($row, 'Family') ?? '';

            if ($first === '' && $last === '') {
                $this->report->skipped('patients', 'بیمار بدون نام و نام خانوادگی');

                return;
            }

            // Sex was a bit column: 1 = female in the legacy form.
            $sex = $this->col($row, 'Sex');
            $gender = $sex === null ? null : ((int) $sex === 1 ? 'f' : 'm');

            $nationalCode = preg_replace('/\D/', '', (string) $this->str($row, 'CodeMelli'));
            $mobile = $this->normalizeMobile($this->str($row, 'Mobile'));

            $registered = $this->date($row, 'CreateDate');

            if (! $registered) {
                // A file must have a registration date; fall back to the
                // earliest treatment, then to today, and note the guess.
                $registered = now()->toDateString();
                $this->report->skipped('patients', 'تاریخ ثبت نامعتبر بود و با تاریخ امروز جایگزین شد', 0);
            }

            $this->store('patients', $legacyId, Patient::class, [
                'code' => $this->int($row, 'Code') ?: $legacyId,
                'first_name' => $first ?: '—',
                'last_name' => $last ?: '—',
                'father_name' => $this->str($row, 'FatherName'),
                // Legacy column name carries a typo: "Birhdate".
                'birth_date' => $this->date($row, 'Birhdate', 'Birthdate', 'BirthDate'),
                'registered_on' => $registered,
                'gender' => $gender,
                'national_code' => strlen($nationalCode) === 10 ? $nationalCode : null,
                'mobile' => $mobile,
                'home_phone' => $this->str($row, 'HomeTell'),
                'work_phone' => $this->str($row, 'WorkTell'),
                'home_address' => $this->str($row, 'HomeAddress'),
                'work_address' => $this->str($row, 'WorkAddress'),
                'job' => $this->str($row, 'Job'),
                'referrer_name' => $this->str($row, 'RefrenceName'),
                'binder_code' => $this->str($row, 'CodeZonkan'),
                'medical_summary' => $this->str($row, 'Khowlage'),
                'description' => $this->str($row, 'Description'),
                'notes' => $this->str($row, 'Molahezat'),
                'insurance_id' => $this->ref('insurances', $this->col($row, 'BimehID')),
            ]);
        });
    }

    /** Iranian mobile numbers, normalised to 09xxxxxxxxx or dropped. */
    private function normalizeMobile(?string $value): ?string
    {
        $v = preg_replace('/\D/', '', JalaliDate::toEnglishDigits((string) $value));

        if ($v === '') {
            return null;
        }

        if (str_starts_with($v, '0098')) {
            $v = '0'.substr($v, 4);
        } elseif (str_starts_with($v, '98') && strlen($v) === 12) {
            $v = '0'.substr($v, 2);
        } elseif (strlen($v) === 10 && str_starts_with($v, '9')) {
            $v = '0'.$v;
        }

        return preg_match('/^09\d{9}$/', $v) ? $v : null;
    }

    /**
     * tblOpration. The comma-separated `ToothName` column becomes rows in
     * treatment_teeth; non-tooth markers such as "bitewing" are appended to
     * the description so no information is lost.
     */
    private function importTreatments(): void
    {
        $this->each('tblOpration', function (array $row) {
            $this->report->read('treatments');

            $legacyId = $this->int($row, 'ID');
            $patientId = $this->ref('patients', $this->col($row, 'CustomerID'));
            $serviceId = $this->ref('treatment_services', $this->col($row, 'SubTitleID'));
            $performedOn = $this->date($row, 'Date');

            if (! $patientId) {
                $this->report->skipped('treatments', 'درمان بدون بیمار معتبر');

                return;
            }

            if (! $serviceId) {
                $this->report->skipped('treatments', 'درمان بدون زیردرمان معتبر');

                return;
            }

            if (! $performedOn) {
                $this->report->skipped('treatments', 'تاریخ درمان قابل تبدیل نبود');

                return;
            }

            $parsed = Teeth::fromLegacyString($this->str($row, 'ToothName'));
            $description = $this->str($row, 'Description');

            if ($parsed['flags']) {
                $description = trim($description.' ['.implode(', ', $parsed['flags']).']');
            }

            if ($this->dryRun) {
                $this->report->imported('treatments');

                return;
            }

            $treatment = Treatment::query()->updateOrCreate(
                ['legacy_id' => $legacyId],
                [
                    'patient_id' => $patientId,
                    'treatment_service_id' => $serviceId,
                    'user_id' => $this->ref('users', $this->col($row, 'UserID')),
                    'performed_on' => $performedOn,
                    'amount' => max(0, $this->money($row, 'Money')),
                    'description' => $description ?: null,
                ],
            );

            $treatment->syncTeeth($parsed['teeth']);

            $treatment->wasRecentlyCreated
                ? $this->report->imported('treatments')
                : $this->report->updated('treatments');
        });
    }

    private function importPayments(): void
    {
        $this->each('tblPay', function (array $row) {
            $this->report->read('payments');

            $patientId = $this->ref('patients', $this->col($row, 'CustomerID'));
            $paidOn = $this->date($row, 'Date');

            if (! $patientId) {
                $this->report->skipped('payments', 'پرداخت بدون بیمار معتبر');

                return;
            }

            if (! $paidOn) {
                $this->report->skipped('payments', 'تاریخ پرداخت قابل تبدیل نبود');

                return;
            }

            $time = $this->str($row, 'Time');
            $time = $time && preg_match('/^\d{1,2}:\d{2}/', JalaliDate::toEnglishDigits($time))
                ? JalaliDate::toEnglishDigits($time)
                : null;

            $this->store('payments', $this->int($row, 'ID'), Payment::class, [
                'patient_id' => $patientId,
                'payment_type_id' => $this->ref('payment_types', $this->col($row, 'PayType')),
                'user_id' => $this->ref('users', $this->col($row, 'UserID')),
                'paid_on' => $paidOn,
                'paid_at' => $time,
                'amount' => max(0, $this->money($row, 'Money')),
                'discount' => max(0, $this->money($row, 'Takhfif')),
                'description' => $this->str($row, 'Description'),
            ]);
        });
    }

    private function importStockMovements(): void
    {
        $this->each('tblStoreUsage', function (array $row) {
            $this->report->read('stock_movements');

            $itemId = $this->ref('stock_items', $this->col($row, 'StoreID'));
            $movedOn = $this->date($row, 'DateTime', 'Date');

            if (! $itemId) {
                $this->report->skipped('stock_movements', 'گردش انبار بدون کالای معتبر');

                return;
            }

            if (! $movedOn) {
                $this->report->skipped('stock_movements', 'تاریخ گردش انبار قابل تبدیل نبود');

                return;
            }

            // Legacy `Type`: 1 = ورود (in), 0 = خروج (out).
            $this->store('stock_movements', $this->int($row, 'ID'), StockMovement::class, [
                'stock_item_id' => $itemId,
                'user_id' => $this->ref('users', $this->col($row, 'UserID')),
                'direction' => (int) $this->col($row, 'Type') === 1 ? 'in' : 'out',
                'quantity' => max(0, abs($this->int($row, 'Num'))),
                'moved_on' => $movedOn,
                'description' => $this->str($row, 'Description'),
            ]);
        });
    }

    // ── Radiography ──────────────────────────────────────────────────────

    /**
     * tblImages held only a filename; the pixels lived on the SMB share
     * \\server\AppIMG\. Point LEGACY_IMAGES_PATH at a mount of that share and
     * the file is copied onto the configured disk under a random name. With
     * no mount available the metadata is still imported, so the record and
     * its tooth links survive and the file can be attached later.
     */
    private function importRadiographs(): void
    {
        $sourceDir = rtrim((string) env('LEGACY_IMAGES_PATH', ''), '/\\');
        $haveSource = $sourceDir !== '' && is_dir($sourceDir);

        if (! $haveSource) {
            $this->say('   LEGACY_IMAGES_PATH در دسترس نیست — فقط اطلاعات تصاویر وارد می‌شود، نه خود فایل‌ها');
        }

        $disk = config('clinic.images.disk');

        $this->each('tblImages', function (array $row) use ($sourceDir, $haveSource, $disk) {
            $this->report->read('radiographs');

            $patientId = $this->ref('patients', $this->col($row, 'CustomerID'));
            $takenOn = $this->date($row, 'Date');
            $filename = $this->str($row, 'Filename');

            if (! $patientId) {
                $this->report->skipped('radiographs', 'تصویر بدون بیمار معتبر');

                return;
            }

            if (! $takenOn) {
                $this->report->skipped('radiographs', 'تاریخ تصویر قابل تبدیل نبود');

                return;
            }

            $parsed = Teeth::fromLegacyString($this->str($row, 'ToothName'));
            $legacyId = $this->int($row, 'ID');

            if ($this->dryRun) {
                $this->report->imported('radiographs');

                return;
            }

            $attributes = [
                'patient_id' => $patientId,
                'user_id' => null,
                'taken_on' => $takenOn,
                'subject' => $this->str($row, 'Subject'),
                'original_name' => $filename,
                'disk' => $disk,
            ];

            $existing = Radiograph::query()->where('legacy_id', $legacyId)->first();

            // Copy the file once. A re-run keeps whatever is already stored.
            if ($haveSource && $filename && ! ($existing?->path)) {
                $source = $sourceDir.DIRECTORY_SEPARATOR.$filename;

                if (is_file($source) && is_readable($source)) {
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg');
                    $path = 'radiographs/'.substr($takenOn, 0, 7).'/'.Str::uuid()->toString().'.'.$extension;

                    Storage::disk($disk)->put($path, file_get_contents($source));

                    $attributes['path'] = $path;
                    $attributes['size'] = filesize($source) ?: null;
                    $attributes['checksum'] = hash_file('sha256', $source) ?: null;
                    $attributes['mime'] = mime_content_type($source) ?: null;

                    if ($dimensions = @getimagesize($source)) {
                        $attributes['width'] = $dimensions[0];
                        $attributes['height'] = $dimensions[1];
                    }
                } else {
                    $this->report->skipped('radiographs', 'فایل تصویر روی مسیر مبدا پیدا نشد', 0);
                }
            }

            // `path` is NOT NULL; use a placeholder when the file is missing so
            // the clinical record still exists. Radiograph::exists() reports false.
            $attributes['path'] ??= $existing?->path ?? '';

            $radiograph = Radiograph::query()->updateOrCreate(['legacy_id' => $legacyId], $attributes);
            $radiograph->syncTeeth($parsed['teeth']);

            $radiograph->wasRecentlyCreated
                ? $this->report->imported('radiographs')
                : $this->report->updated('radiographs');
        });
    }

    /** tblSMS — the outbox history. `Enable` was the legacy sent flag. */
    private function importSms(): void
    {
        $this->each('tblSMS', function (array $row) {
            $this->report->read('sms');

            $mobile = $this->normalizeMobile($this->str($row, 'Number'));
            $body = $this->str($row, 'Text');

            if (! $mobile || ! $body) {
                $this->report->skipped('sms', 'پیامک بدون شماره معتبر یا بدون متن');

                return;
            }

            $sentAt = JalaliDate::parse($this->str($row, 'Date'));

            $this->store('sms_messages', $this->int($row, 'ID'), SmsMessage::class, [
                'patient_id' => null,
                'user_id' => $this->ref('users', $this->col($row, 'UserID')),
                'mobile' => $mobile,
                'body' => $body,
                'kind' => 'legacy',
                'status' => (int) ($this->col($row, 'Enable') ?? 0) === 1 ? 'sent' : 'queued',
                'provider' => 'candoosms',
                'sent_at' => $sentAt,
            ]);
        });
    }

    /** Resolve a legacy foreign key to the new primary key. */
    private function ref(string $bucket, mixed $legacyId): ?int
    {
        $legacyId = (int) $legacyId;

        if ($legacyId <= 0) {
            return null;
        }

        if (isset($this->map[$bucket][$legacyId])) {
            return $this->map[$bucket][$legacyId];
        }

        // Rebuild lazily when a step is run in isolation.
        $model = [
            'insurances' => Insurance::class,
            'payment_types' => PaymentType::class,
            'treatment_services' => TreatmentService::class,
            'treatment_categories' => TreatmentCategory::class,
            'drugs' => Drug::class,
            'stock_items' => StockItem::class,
            'users' => User::class,
            'patients' => Patient::class,
        ][$bucket] ?? null;

        if (! $model) {
            return null;
        }

        $id = $model::query()->where('legacy_id', $legacyId)->value('id');

        return $this->map[$bucket][$legacyId] = $id;
    }
}
