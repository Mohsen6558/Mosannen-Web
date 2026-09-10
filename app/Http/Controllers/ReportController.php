<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Services\ReportService;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Replaces frmReport, which had five tabs each building SQL by string
 * concatenation. Six reports now, driven by one date range; the heavy
 * aggregations live in ReportService.
 *
 * Only the active tab's data is computed — the receivables aging walks every
 * treatment, and paying for it while someone reads the patient mix would be
 * wasteful.
 */
class ReportController extends Controller implements HasMiddleware
{
    private const TABS = ['financial', 'receivables', 'clinical', 'practitioners', 'patients', 'appointments'];

    public function __construct(private readonly ReportService $reports) {}

    public static function middleware(): array
    {
        return ['permission:reports.view'];
    }

    public function index(Request $request): Response
    {
        [$from, $to] = $this->range($request);

        $tab = in_array($request->query('report'), self::TABS, true)
            ? $request->query('report')
            : 'financial';

        $canSeeMoney = $request->user()->can('reports.financial');

        // A member of staff without financial access lands on a tab they can read.
        if (! $canSeeMoney && in_array($tab, ['financial', 'receivables', 'practitioners'], true)) {
            $tab = 'clinical';
        }

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to, 'report' => $tab],
            'canSeeMoney' => $canSeeMoney,
            'tabs' => $this->visibleTabs($canSeeMoney),
            'data' => $this->dataFor($tab, $from, $to, $canSeeMoney),
        ]);
    }

    /** @return array<string, mixed> */
    private function dataFor(string $tab, string $from, string $to, bool $canSeeMoney): array
    {
        return match ($tab) {
            'financial' => [
                'summary' => [
                    'collected' => (int) Payment::between($from, $to)->sum('amount'),
                    'discount' => (int) Payment::between($from, $to)->sum('discount'),
                    'billed' => (int) Treatment::between($from, $to)->sum('amount'),
                    'transactions' => Payment::between($from, $to)->count(),
                ],
                'monthly' => $this->reports->monthlyBilledVsCollected(12),
                'daily' => $this->reports->dailyIncome($from, $to),
                'byType' => $this->reports->incomeByPaymentType($from, $to),
            ],

            'receivables' => $this->reports->receivablesAging(),

            'clinical' => [
                'services' => $this->maskMoney($this->reports->servicesPerformed($from, $to), $canSeeMoney),
                'categories' => $this->maskMoney($this->reports->treatmentsByCategory($from, $to), $canSeeMoney),
                'teeth' => $this->reports->toothFrequency($from, $to),
                'total' => Treatment::between($from, $to)->count(),
            ],

            'practitioners' => [
                'rows' => $this->reports->practitionerPerformance($from, $to),
            ],

            'patients' => [
                'monthly' => $this->reports->newPatientsByMonth(12),
                'mix' => $this->reports->patientMix(),
                'referrals' => $this->reports->referralSources(),
                'recall' => $this->reports->recallList(6),
                'total' => Patient::count(),
                'new' => Patient::whereBetween('registered_on', [$from, $to])->count(),
            ],

            'appointments' => $this->reports->appointmentAdherence($from, $to),

            default => [],
        };
    }

    /** Strip money columns for staff who may see counts but not values. */
    private function maskMoney(array $rows, bool $canSeeMoney): array
    {
        if ($canSeeMoney) {
            return $rows;
        }

        return array_map(fn (array $r) => [...$r, 'total' => null], $rows);
    }

    /** @return list<array{key: string, label: string}> */
    private function visibleTabs(bool $canSeeMoney): array
    {
        $all = [
            ['key' => 'financial', 'label' => 'مالی', 'money' => true],
            ['key' => 'receivables', 'label' => 'مطالبات', 'money' => true],
            ['key' => 'clinical', 'label' => 'درمان', 'money' => false],
            ['key' => 'practitioners', 'label' => 'پزشکان', 'money' => true],
            ['key' => 'patients', 'label' => 'بیماران', 'money' => false],
            ['key' => 'appointments', 'label' => 'نوبت‌ها', 'money' => false],
        ];

        return array_values(array_map(
            fn ($t) => ['key' => $t['key'], 'label' => $t['label']],
            array_filter($all, fn ($t) => $canSeeMoney || ! $t['money']),
        ));
    }

    /** Debtor list as CSV — the report staff actually chase. */
    public function exportDebtors(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $aging = $this->reports->receivablesAging();

        return $this->csv('debtors-'.now()->format('Y-m-d').'.csv',
            ['کد پرونده', 'نام بیمار', 'موبایل', 'مانده', 'قدیمی‌ترین درمان تسویه‌نشده', 'تا ۳۰ روز', '۳۱ تا ۶۰', '۶۱ تا ۹۰', 'بیش از ۹۰'],
            array_map(fn ($r) => [
                $r['code'], $r['name'], $r['mobile'], $r['outstanding'],
                $r['oldest_unpaid'] ? JalaliDate::format($r['oldest_unpaid'], persianDigits: false) : '',
                $r['buckets']['0-30'], $r['buckets']['31-60'], $r['buckets']['61-90'], $r['buckets']['90+'],
            ], $aging['rows']),
        );
    }

    /** Recall list as CSV, for a bulk SMS run. */
    public function exportRecall(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $rows = $this->reports->recallList((int) $request->query('months', 6));

        return $this->csv('recall-'.now()->format('Y-m-d').'.csv',
            ['کد پرونده', 'نام بیمار', 'موبایل', 'آخرین مراجعه', 'ماه از آخرین مراجعه'],
            array_map(fn ($r) => [
                $r['code'], $r['name'], $r['mobile'],
                $r['last_visit'] ? JalaliDate::format($r['last_visit'], persianDigits: false) : '',
                $r['months'],
            ], $rows),
        );
    }

    private function csv(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens the Persian text as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:string,1:string} */
    private function range(Request $request): array
    {
        return [
            $request->query('from') ?: now()->startOfMonth()->toDateString(),
            $request->query('to') ?: now()->toDateString(),
        ];
    }
}
