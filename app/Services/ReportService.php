<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregations behind the reports screen.
 *
 * Money is summed as integer Rial throughout. Anything that returns a date
 * returns an ISO string; the frontend converts to Jalali.
 */
class ReportService
{
    // ── Financial ────────────────────────────────────────────────────────

    /**
     * Billed against collected, by month. Two measures of the same unit, so
     * they belong on one axis.
     *
     * @return list<array{month: string, billed: int, collected: int, discount: int}>
     */
    public function monthlyBilledVsCollected(int $months = 12): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);

        $billed = Treatment::query()
            ->where('performed_on', '>=', $from->toDateString())
            ->selectRaw("to_char(performed_on, 'YYYY-MM') AS m, SUM(amount) AS total")
            ->groupBy('m')->pluck('total', 'm');

        $collected = Payment::query()
            ->where('paid_on', '>=', $from->toDateString())
            ->selectRaw("to_char(paid_on, 'YYYY-MM') AS m, SUM(amount) AS total, SUM(discount) AS disc")
            ->groupBy('m')->get()->keyBy('m');

        $out = [];

        for ($i = 0; $i < $months; $i++) {
            $key = $from->copy()->addMonths($i)->format('Y-m');
            $out[] = [
                'month' => $key.'-01',
                'billed' => (int) ($billed[$key] ?? 0),
                'collected' => (int) ($collected[$key]->total ?? 0),
                'discount' => (int) ($collected[$key]->disc ?? 0),
            ];
        }

        return $out;
    }

    /** @return list<array{date: string, total: int, count: int}> */
    public function dailyIncome(string $from, string $to): array
    {
        $rows = Payment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->selectRaw('paid_on, SUM(amount) AS total, COUNT(*) AS count')
            ->groupBy('paid_on')->orderBy('paid_on')->get();

        return $rows->map(fn ($r) => [
            'date' => substr((string) $r->paid_on, 0, 10),
            'total' => (int) $r->total,
            'count' => (int) $r->count,
        ])->all();
    }

    /** @return list<array{type: string, total: int, count: int}> */
    public function incomeByPaymentType(string $from, string $to): array
    {
        return Payment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->selectRaw('payment_type_id, SUM(amount) AS total, COUNT(*) AS count')
            ->groupBy('payment_type_id')
            ->with('paymentType:id,name')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->paymentType?->name ?? 'نامشخص',
                'total' => (int) $r->total,
                'count' => (int) $r->count,
            ])
            ->sortByDesc('total')->values()->all();
    }

    // ── Receivables ──────────────────────────────────────────────────────

    /**
     * Receivables aged by how long the unpaid work has been outstanding.
     *
     * Payments are applied oldest-treatment-first (FIFO), which is what a
     * running balance means in practice: money received settles the earliest
     * work. Whatever is still uncovered is bucketed by the age of the
     * treatment it belongs to — so a patient who paid last week but owes for
     * work from a year ago lands in the 90+ bucket, which is the point.
     *
     * @return array{buckets: list<array{key: string, label: string, total: int, patients: int}>, rows: list<array<string,mixed>>, total: int}
     */
    public function receivablesAging(): array
    {
        $definitions = [
            ['key' => '0-30', 'label' => 'تا ۳۰ روز', 'min' => 0, 'max' => 30],
            ['key' => '31-60', 'label' => '۳۱ تا ۶۰ روز', 'min' => 31, 'max' => 60],
            ['key' => '61-90', 'label' => '۶۱ تا ۹۰ روز', 'min' => 61, 'max' => 90],
            ['key' => '90+', 'label' => 'بیش از ۹۰ روز', 'min' => 91, 'max' => PHP_INT_MAX],
        ];

        $buckets = [];
        foreach ($definitions as $d) {
            $buckets[$d['key']] = ['key' => $d['key'], 'label' => $d['label'], 'total' => 0, 'patients' => 0];
        }

        $rows = [];
        $today = Carbon::today();

        Patient::query()
            ->select(['id', 'code', 'first_name', 'last_name', 'mobile'])
            ->with([
                'treatments' => fn ($q) => $q->select(['id', 'patient_id', 'performed_on', 'amount'])->orderBy('performed_on')->orderBy('id'),
            ])
            ->withSum('payments as settled', DB::raw('amount + discount'))
            ->chunk(200, function ($patients) use (&$buckets, &$rows, $definitions, $today) {
                foreach ($patients as $patient) {
                    $remaining = (int) ($patient->settled ?? 0);
                    $perBucket = array_fill_keys(array_column($definitions, 'key'), 0);
                    $outstanding = 0;
                    $oldestUnpaid = null;

                    foreach ($patient->treatments as $treatment) {
                        $amount = (int) $treatment->amount;

                        // Settle this treatment from the money already received.
                        $applied = min($remaining, $amount);
                        $remaining -= $applied;
                        $unpaid = $amount - $applied;

                        if ($unpaid <= 0) {
                            continue;
                        }

                        $ageDays = $treatment->performed_on
                            ? (int) $today->diffInDays(Carbon::parse($treatment->performed_on), absolute: true)
                            : 0;

                        foreach ($definitions as $d) {
                            if ($ageDays >= $d['min'] && $ageDays <= $d['max']) {
                                $perBucket[$d['key']] += $unpaid;
                                break;
                            }
                        }

                        $outstanding += $unpaid;
                        $oldestUnpaid ??= $treatment->performed_on?->toDateString();
                    }

                    if ($outstanding <= 0) {
                        continue;
                    }

                    foreach ($perBucket as $key => $value) {
                        if ($value > 0) {
                            $buckets[$key]['total'] += $value;
                            $buckets[$key]['patients']++;
                        }
                    }

                    $rows[] = [
                        'id' => $patient->id,
                        'code' => $patient->code,
                        'name' => $patient->full_name,
                        'mobile' => $patient->mobile,
                        'outstanding' => $outstanding,
                        'oldest_unpaid' => $oldestUnpaid,
                        'buckets' => $perBucket,
                    ];
                }
            });

        usort($rows, fn ($a, $b) => $b['outstanding'] <=> $a['outstanding']);

        return [
            'buckets' => array_values($buckets),
            'rows' => array_slice($rows, 0, 300),
            'total' => array_sum(array_column($buckets, 'total')),
        ];
    }

    // ── Clinical ─────────────────────────────────────────────────────────

    /** @return list<array{name: string, category: ?string, count: int, total: int}> */
    public function servicesPerformed(string $from, string $to): array
    {
        return Treatment::query()
            ->whereBetween('performed_on', [$from, $to])
            ->selectRaw('treatment_service_id, COUNT(*) AS count, SUM(amount) AS total')
            ->groupBy('treatment_service_id')
            ->with('service:id,name,treatment_category_id', 'service.category:id,name')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->service?->name ?? 'نامشخص',
                'category' => $r->service?->category?->name,
                'count' => (int) $r->count,
                'total' => (int) $r->total,
            ])
            ->sortByDesc('count')->values()->all();
    }

    /** @return list<array{name: string, count: int, total: int}> */
    public function treatmentsByCategory(string $from, string $to): array
    {
        return Treatment::query()
            ->join('treatment_services', 'treatments.treatment_service_id', '=', 'treatment_services.id')
            ->join('treatment_categories', 'treatment_services.treatment_category_id', '=', 'treatment_categories.id')
            ->whereBetween('treatments.performed_on', [$from, $to])
            ->whereNull('treatments.deleted_at')
            ->selectRaw('treatment_categories.name, COUNT(*) AS count, SUM(treatments.amount) AS total')
            ->groupBy('treatment_categories.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * How often each tooth was worked on — the one report that is specific to
     * a dental practice. Rendered as a heat map over the chart.
     *
     * @return array<string, int> FDI code => treatment count
     */
    public function toothFrequency(string $from, string $to): array
    {
        return DB::table('treatment_teeth')
            ->join('treatments', 'treatment_teeth.treatment_id', '=', 'treatments.id')
            ->whereBetween('treatments.performed_on', [$from, $to])
            ->whereNull('treatments.deleted_at')
            ->selectRaw('treatment_teeth.tooth_code, COUNT(*) AS count')
            ->groupBy('treatment_teeth.tooth_code')
            ->pluck('count', 'tooth_code')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // ── Practitioners ────────────────────────────────────────────────────

    /** @return list<array{name: string, count: int, total: int, patients: int, average: int}> */
    public function practitionerPerformance(string $from, string $to): array
    {
        return Treatment::query()
            ->whereBetween('performed_on', [$from, $to])
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) AS count, SUM(amount) AS total, COUNT(DISTINCT patient_id) AS patients')
            ->groupBy('user_id')
            ->with('user:id,full_name,name,username')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->user?->display_name ?? 'نامشخص',
                'count' => (int) $r->count,
                'total' => (int) $r->total,
                'patients' => (int) $r->patients,
                'average' => (int) $r->count > 0 ? intdiv((int) $r->total, (int) $r->count) : 0,
            ])
            ->sortByDesc('total')->values()->all();
    }

    // ── Patients ─────────────────────────────────────────────────────────

    /** @return list<array{month: string, count: int}> */
    public function newPatientsByMonth(int $months = 12): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);

        $rows = Patient::query()
            ->where('registered_on', '>=', $from->toDateString())
            ->selectRaw("to_char(registered_on, 'YYYY-MM') AS m, COUNT(*) AS count")
            ->groupBy('m')->pluck('count', 'm');

        $out = [];

        for ($i = 0; $i < $months; $i++) {
            $key = $from->copy()->addMonths($i)->format('Y-m');
            $out[] = ['month' => $key.'-01', 'count' => (int) ($rows[$key] ?? 0)];
        }

        return $out;
    }

    /** @return array{insurance: list<array{name: string, count: int}>, gender: list<array{name: string, count: int}>, age: list<array{name: string, count: int}>} */
    public function patientMix(): array
    {
        $insurance = Patient::query()
            ->selectRaw('insurance_id, COUNT(*) AS count')
            ->groupBy('insurance_id')
            ->with('insurance:id,name')
            ->get()
            ->map(fn ($r) => ['name' => $r->insurance?->name ?? 'آزاد', 'count' => (int) $r->count])
            ->sortByDesc('count')->values()->all();

        $gender = Patient::query()
            ->selectRaw('gender, COUNT(*) AS count')
            ->groupBy('gender')
            ->get()
            ->map(fn ($r) => [
                'name' => match ($r->gender) {
                    'm' => 'مرد', 'f' => 'زن', default => 'نامشخص'
                },
                'count' => (int) $r->count,
            ])
            ->all();

        // Age bands that matter clinically: children, teens, adults, older adults.
        $age = Patient::query()
            ->whereNotNull('birth_date')
            ->selectRaw("
                CASE
                    WHEN date_part('year', age(birth_date)) < 13 THEN 'زیر ۱۳ سال'
                    WHEN date_part('year', age(birth_date)) < 20 THEN '۱۳ تا ۱۹ سال'
                    WHEN date_part('year', age(birth_date)) < 40 THEN '۲۰ تا ۳۹ سال'
                    WHEN date_part('year', age(birth_date)) < 60 THEN '۴۰ تا ۵۹ سال'
                    ELSE '۶۰ سال به بالا'
                END AS band,
                COUNT(*) AS count
            ")
            ->groupBy('band')
            ->get()
            ->map(fn ($r) => ['name' => $r->band, 'count' => (int) $r->count])
            ->all();

        // Keep the clinical order rather than sorting by size.
        $order = ['زیر ۱۳ سال', '۱۳ تا ۱۹ سال', '۲۰ تا ۳۹ سال', '۴۰ تا ۵۹ سال', '۶۰ سال به بالا'];
        usort($age, fn ($a, $b) => array_search($a['name'], $order, true) <=> array_search($b['name'], $order, true));

        return ['insurance' => $insurance, 'gender' => $gender, 'age' => $age];
    }

    /** Where patients came from. @return list<array{name: string, count: int}> */
    public function referralSources(int $limit = 12): array
    {
        return Patient::query()
            ->whereNotNull('referrer_name')
            ->where('referrer_name', '!=', '')
            ->selectRaw('referrer_name, COUNT(*) AS count')
            ->groupBy('referrer_name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['name' => $r->referrer_name, 'count' => (int) $r->count])
            ->all();
    }

    /**
     * Patients with no treatment for a while, who have a mobile number.
     * This is a call list, not a statistic — it feeds the SMS screen.
     *
     * @return list<array{id: int, code: int, name: string, mobile: ?string, last_visit: ?string, months: int}>
     */
    public function recallList(int $months = 6, int $limit = 300): array
    {
        $cutoff = now()->subMonths($months)->toDateString();

        return Patient::query()
            ->whereNotNull('mobile')
            ->withMax('treatments as last_visit', 'performed_on')
            ->get(['id', 'code', 'first_name', 'last_name', 'mobile'])
            ->filter(fn (Patient $p) => $p->last_visit !== null && $p->last_visit < $cutoff)
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->full_name,
                'mobile' => $p->mobile,
                'last_visit' => $p->last_visit,
                'months' => (int) Carbon::parse($p->last_visit)->diffInMonths(now()),
            ])
            ->sortByDesc('months')
            ->take($limit)
            ->values()
            ->all();
    }

    // ── Appointments ─────────────────────────────────────────────────────

    /** @return array{statuses: list<array{key: string, name: string, count: int}>, total: int, byHour: list<array{hour: int, count: int}>, byWeekday: list<array{name: string, count: int}>} */
    public function appointmentAdherence(string $from, string $to): array
    {
        $labels = [
            'scheduled' => 'ثبت‌شده',
            'confirmed' => 'تایید‌شده',
            'done' => 'انجام‌شده',
            'cancelled' => 'لغو‌شده',
            'no_show' => 'غایب',
        ];

        $counts = Appointment::query()
            ->whereBetween('scheduled_on', [$from, $to])
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')->pluck('count', 'status');

        $statuses = [];
        foreach ($labels as $key => $name) {
            $statuses[] = ['key' => $key, 'name' => $name, 'count' => (int) ($counts[$key] ?? 0)];
        }

        $byHour = Appointment::query()
            ->whereBetween('scheduled_on', [$from, $to])
            ->selectRaw("date_part('hour', starts_at) AS h, COUNT(*) AS count")
            ->groupBy('h')->pluck('count', 'h');

        $hours = [];
        for ($h = 8; $h <= 21; $h++) {
            $hours[] = ['hour' => $h, 'count' => (int) ($byHour[$h] ?? 0)];
        }

        // PostgreSQL dow: 0=Sunday. The Iranian week starts on Saturday.
        $weekNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        $byDow = Appointment::query()
            ->whereBetween('scheduled_on', [$from, $to])
            ->selectRaw("date_part('dow', scheduled_on) AS d, COUNT(*) AS count")
            ->groupBy('d')->pluck('count', 'd');

        $weekdays = [];
        for ($i = 0; $i < 7; $i++) {
            $pgDow = ($i + 6) % 7; // Saturday(6) → index 0
            $weekdays[] = ['name' => $weekNames[$i], 'count' => (int) ($byDow[$pgDow] ?? 0)];
        }

        return [
            'statuses' => $statuses,
            'total' => array_sum(array_column($statuses, 'count')),
            'byHour' => $hours,
            'byWeekday' => $weekdays,
        ];
    }
}
