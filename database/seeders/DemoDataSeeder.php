<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Drug;
use App\Models\DrugVariant;
use App\Models\Insurance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentService;
use App\Models\User;
use App\Support\Teeth;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Realistic demo data for local development and the test suite.
 * Never runs in production — see DatabaseSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $insurances = collect(['آزاد', 'تامین اجتماعی', 'خدمات درمانی', 'نیروهای مسلح', 'بیمه ایران'])
            ->map(fn ($name, $i) => Insurance::firstOrCreate(['name' => $name], ['item_order' => $i]));

        $payTypes = collect(['نقدی', 'کارتخوان', 'کارت به کارت', 'چک'])
            ->map(fn ($name, $i) => PaymentType::firstOrCreate(['name' => $name], ['item_order' => $i]));

        $catalog = [
            'ترمیمی' => [['ترمیم آمالگام یک سطحی', 2_500_000], ['ترمیم کامپوزیت یک سطحی', 4_000_000], ['ترمیم کامپوزیت دو سطحی', 5_500_000]],
            'عصب‌کشی' => [['درمان ریشه تک کاناله', 12_000_000], ['درمان ریشه دو کاناله', 16_000_000], ['درمان ریشه سه کاناله', 20_000_000]],
            'جراحی' => [['کشیدن دندان ساده', 3_000_000], ['کشیدن دندان عقل نهفته', 18_000_000], ['ژنژیوکتومی', 6_000_000]],
            'پروتز' => [['روکش PFM', 22_000_000], ['روکش زیرکونیا', 38_000_000], ['ونیر کامپوزیت', 15_000_000]],
            'زیبایی' => [['بلیچینگ دو فک', 25_000_000], ['جرم‌گیری و بروساژ', 4_500_000]],
            'اطفال' => [['فلوراید تراپی', 2_000_000], ['فیشورسیلانت', 2_800_000], ['پالپوتومی', 7_000_000]],
        ];

        $services = collect();

        foreach (array_values($catalog) as $ci => $items) {
            $category = TreatmentCategory::firstOrCreate(
                ['name' => array_keys($catalog)[$ci]],
                ['item_order' => $ci],
            );

            foreach ($items as $si => [$name, $price]) {
                $services->push(TreatmentService::firstOrCreate(
                    ['name' => $name, 'treatment_category_id' => $category->id],
                    ['price' => $price, 'item_order' => $si],
                ));
            }
        }

        foreach ([
            'آموکسی‌سیلین' => ['کپسول ۵۰۰ میلی‌گرم', 'شربت ۲۵۰ میلی‌گرم'],
            'ایبوپروفن' => ['قرص ۴۰۰ میلی‌گرم', 'ژل موضعی'],
            'مترونیدازول' => ['قرص ۲۵۰ میلی‌گرم'],
            'کلرهگزیدین' => ['دهانشویه ۰.۲٪'],
            'ژلوفن' => ['کپسول ۴۰۰ میلی‌گرم'],
        ] as $drugName => $variants) {
            $drug = Drug::firstOrCreate(['name' => $drugName]);

            foreach ($variants as $i => $variant) {
                DrugVariant::firstOrCreate(['drug_id' => $drug->id, 'name' => $variant], [
                    'default_dosage' => 'هر ۸ ساعت یک عدد',
                    'default_instructions' => 'بعد از غذا',
                    'item_order' => $i,
                ]);
            }
        }

        $doctor = User::firstOrCreate(['username' => 'doctor'], [
            'name' => 'دکتر سارا محمدی',
            'full_name' => 'دکتر سارا محمدی',
            'password' => 'password',
            'is_active' => true,
        ]);
        $doctor->syncRoles(['doctor']);

        $reception = User::firstOrCreate(['username' => 'reception'], [
            'name' => 'زهرا کریمی',
            'full_name' => 'زهرا کریمی',
            'password' => 'password',
            'is_active' => true,
        ]);
        $reception->syncRoles(['reception']);

        foreach ([
            ['گاز استریل', 'بسته', 20], ['دستکش لاتکس', 'جعبه', 15], ['ماسک سه‌لایه', 'جعبه', 10],
            ['آمالگام کپسولی', 'عدد', 50], ['کامپوزیت A2', 'سرنگ', 8], ['سوزن بی‌حسی', 'عدد', 100],
            ['کارپول لیدوکائین', 'عدد', 80],
        ] as $i => [$name, $unit, $reorder]) {
            $item = StockItem::firstOrCreate(['name' => $name], [
                'unit' => $unit, 'reorder_level' => $reorder, 'item_order' => $i,
            ]);

            StockMovement::firstOrCreate(
                ['stock_item_id' => $item->id, 'direction' => 'in', 'moved_on' => now()->subMonths(2)->toDateString()],
                ['quantity' => $reorder * random_int(2, 6), 'user_id' => $reception->id, 'description' => 'موجودی اولیه'],
            );
        }

        $this->makePatients($insurances, $payTypes, $services, $doctor, $reception);
    }

    private function makePatients($insurances, $payTypes, $services, User $doctor, User $reception): void
    {
        $first = ['علی', 'محمد', 'رضا', 'حسین', 'مهدی', 'امیر', 'فاطمه', 'زهرا', 'مریم', 'نرگس', 'سارا', 'الهام', 'نیما', 'بابک', 'شیرین'];
        $last = ['محمدی', 'حسینی', 'رضایی', 'کریمی', 'موسوی', 'احمدی', 'صادقی', 'جعفری', 'قاسمی', 'نوری', 'شریفی', 'اکبری'];
        $teeth = Teeth::all();

        for ($i = 0; $i < 60; $i++) {
            $registered = Carbon::now()->subDays(random_int(1, 900));

            $referrers = [
                null, null, 'تبلیغات اینستاگرام', 'معرفی بیمار قبلی', 'تابلوی مطب',
                'دکتر رحیمی', 'بیمه تامین اجتماعی', 'جستجوی اینترنتی', 'معرفی همکار',
            ];

            $patient = Patient::create([
                'code' => Patient::nextCode(),
                'first_name' => $first[array_rand($first)],
                'last_name' => $last[array_rand($last)],
                'father_name' => $first[array_rand($first)],
                'birth_date' => Carbon::now()->subYears(random_int(6, 75))->subDays(random_int(0, 364)),
                'registered_on' => $registered,
                'gender' => random_int(0, 1) ? 'm' : 'f',
                'mobile' => '09'.random_int(100000000, 399999999),
                'insurance_id' => $insurances->random()->id,
                'created_by' => $reception->id,
                'job' => ['کارمند', 'آزاد', 'دانشجو', 'خانه‌دار', 'بازنشسته'][random_int(0, 4)],
                'referrer_name' => $referrers[array_rand($referrers)],
            ]);

            $billed = 0;

            foreach (range(1, random_int(1, 6)) as $ignored) {
                $service = $services->random();
                $performedOn = $registered->copy()->addDays(random_int(0, max(1, $registered->diffInDays(now()))));

                $treatment = Treatment::create([
                    'patient_id' => $patient->id,
                    'treatment_service_id' => $service->id,
                    'user_id' => $doctor->id,
                    'performed_on' => $performedOn,
                    'amount' => $service->price,
                ]);

                $treatment->syncTeeth(collect($teeth)->random(random_int(1, 3))->all());
                $billed += $service->price;
            }

            // Most patients have paid something; a few still owe the lot.
            $ratio = [0, 0.25, 0.5, 0.8, 1, 1][random_int(0, 5)];
            $target = (int) round($billed * $ratio);
            $paid = 0;

            while ($paid < $target) {
                $amount = min($target - $paid, (int) round($billed / random_int(1, 3) / 100000) * 100000);

                if ($amount <= 0) {
                    break;
                }

                Payment::create([
                    'patient_id' => $patient->id,
                    'payment_type_id' => $payTypes->random()->id,
                    'user_id' => $reception->id,
                    'paid_on' => $registered->copy()->addDays(random_int(0, max(1, $registered->diffInDays(now())))),
                    'paid_at' => sprintf('%02d:%02d', random_int(9, 19), random_int(0, 59)),
                    'amount' => $amount,
                ]);

                $paid += $amount;
            }
        }

        // A handful of appointments across today and the coming days.
        $patients = Patient::inRandomOrder()->limit(18)->get();

        foreach ($patients as $index => $patient) {
            Appointment::create([
                'patient_id' => $patient->id,
                'user_id' => $doctor->id,
                'scheduled_on' => now()->addDays(intdiv($index, 6))->toDateString(),
                'starts_at' => sprintf('%02d:%02d', 9 + ($index % 6) * 2, [0, 30][random_int(0, 1)]),
                'duration_minutes' => [30, 45, 60][random_int(0, 2)],
                'status' => ['scheduled', 'confirmed'][random_int(0, 1)],
            ]);
        }
    }
}
