<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\Radiograph;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Replaces frmReport, which had five separate tabs each building SQL by
 * string concatenation. The same five reports, driven by one date range.
 */
class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['permission:reports.view'];
    }

    public function index(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $canSeeMoney = $request->user()->can('reports.financial');

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to, 'report' => $request->query('report', 'financial')],
            'canSeeMoney' => $canSeeMoney,

            // گزارش مالی — income per day, split by payment method.
            'financial' => $canSeeMoney ? [
                'daily' => Payment::query()->between($from, $to)
                    ->selectRaw('paid_on, SUM(amount) AS total, SUM(discount) AS discount, COUNT(*) AS count')
                    ->groupBy('paid_on')->orderBy('paid_on')
                    ->get()
                    ->map(fn ($r) => [
                        'date' => (string) $r->paid_on,
                        'total' => (int) $r->total,
                        'discount' => (int) $r->discount,
                        'count' => (int) $r->count,
                    ]),
                'by_type' => Payment::query()->between($from, $to)
                    ->selectRaw('payment_type_id, SUM(amount) AS total, COUNT(*) AS count')
                    ->groupBy('payment_type_id')
                    ->with('paymentType:id,name')
                    ->get()
                    ->map(fn ($r) => [
                        'type' => $r->paymentType?->name ?? 'نامشخص',
                        'total' => (int) $r->total,
                        'count' => (int) $r->count,
                    ]),
                'summary' => [
                    'total' => (int) Payment::between($from, $to)->sum('amount'),
                    'discount' => (int) Payment::between($from, $to)->sum('discount'),
                    'billed' => (int) Treatment::between($from, $to)->sum('amount'),
                ],
            ] : null,

            // گزارش زیردرمان — which services were performed, and how often.
            'services' => Treatment::query()->between($from, $to)
                ->selectRaw('treatment_service_id, COUNT(*) AS count, SUM(amount) AS total')
                ->groupBy('treatment_service_id')
                ->orderByDesc('count')
                ->with('service:id,name,treatment_category_id', 'service.category:id,name')
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->service?->name ?? 'نامشخص',
                    'category' => $r->service?->category?->name,
                    'count' => (int) $r->count,
                    'total' => $request->user()->can('reports.financial') ? (int) $r->total : null,
                ]),

            // گزارش عکسبرداری — radiographs per day.
            'radiographs' => Radiograph::query()
                ->whereBetween('taken_on', [$from, $to])
                ->selectRaw('taken_on, COUNT(*) AS count')
                ->groupBy('taken_on')->orderBy('taken_on')
                ->get()
                ->map(fn ($r) => ['date' => (string) $r->taken_on, 'count' => (int) $r->count]),

            // گزارش مریض‌ها — new files registered in the period.
            'patients' => [
                'new_count' => Patient::whereBetween('registered_on', [$from, $to])->count(),
                'by_insurance' => Patient::query()
                    ->whereBetween('registered_on', [$from, $to])
                    ->selectRaw('insurance_id, COUNT(*) AS count')
                    ->groupBy('insurance_id')
                    ->with('insurance:id,name')
                    ->get()
                    ->map(fn ($r) => [
                        'insurance' => $r->insurance?->name ?? 'آزاد',
                        'count' => (int) $r->count,
                    ]),
            ],

            // گزارش بدهکار/بستانکار — replaces dbo.getReminderMoney.
            'debtors' => $canSeeMoney ? $this->debtors() : [],
        ]);
    }

    /** CSV export of the debtor list — the report staff actually chase. */
    public function exportDebtors(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $rows = $this->debtors(limit: 5000);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens the Persian text as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['کد پرونده', 'نام بیمار', 'موبایل', 'مجموع درمان', 'پرداختی', 'تخفیف', 'مانده']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['code'], $r['name'], $r['mobile'],
                    $r['billed'], $r['paid'], $r['discount'], $r['balance'],
                ]);
            }

            fclose($out);
        }, 'debtors-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, array<string, mixed>> */
    private function debtors(int $limit = 200): array
    {
        return Patient::query()
            ->withBalance()
            ->get(['id', 'code', 'first_name', 'last_name', 'mobile'])
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->full_name,
                'mobile' => $p->mobile,
                'billed' => (int) ($p->billed_total ?? 0),
                'paid' => (int) ($p->paid_total ?? 0),
                'discount' => (int) ($p->discount_total ?? 0),
                'balance' => (int) ($p->billed_total ?? 0) - (int) ($p->paid_total ?? 0) - (int) ($p->discount_total ?? 0),
            ])
            ->filter(fn ($r) => $r['balance'] > 0)
            ->sortByDesc('balance')
            ->take($limit)
            ->values()
            ->all();
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
