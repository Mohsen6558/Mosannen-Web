<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\StockItem;
use App\Models\Treatment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $prevMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $prevMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        $canSeeMoney = auth()->user()->can('reports.financial');

        return Inertia::render('Dashboard', [
            'stats' => [
                'patients_total' => Patient::count(),
                'patients_new_this_month' => Patient::where('registered_on', '>=', $monthStart)->count(),
                'treatments_today' => Treatment::whereDate('performed_on', $today)->count(),
                'appointments_today' => Appointment::onDate($today)->whereIn('status', ['scheduled', 'confirmed'])->count(),

                'income_today' => $canSeeMoney ? (int) Payment::whereDate('paid_on', $today)->sum('amount') : null,
                'income_month' => $canSeeMoney ? (int) Payment::where('paid_on', '>=', $monthStart)->sum('amount') : null,
                'income_prev_month' => $canSeeMoney
                    ? (int) Payment::whereBetween('paid_on', [$prevMonthStart, $prevMonthEnd])->sum('amount')
                    : null,
                'outstanding' => $canSeeMoney ? $this->outstandingTotal() : null,
            ],

            // Last 30 days of income, for the dashboard chart.
            'incomeSeries' => $canSeeMoney ? $this->incomeSeries(30) : [],

            'topServices' => $this->topServices($monthStart),

            'todayAppointments' => Appointment::onDate($today)
                ->with('patient:id,code,first_name,last_name,mobile')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Appointment $a) => [
                    'id' => $a->id,
                    'starts_at' => substr((string) $a->starts_at, 0, 5),
                    'duration_minutes' => $a->duration_minutes,
                    'status' => $a->status,
                    'patient' => [
                        'id' => $a->patient?->id,
                        'name' => $a->patient?->full_name,
                        'mobile' => $a->patient?->mobile,
                    ],
                    'notes' => $a->notes,
                ]),

            'recentPatients' => Patient::query()
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['id', 'code', 'first_name', 'last_name', 'registered_on'])
                ->map(fn (Patient $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->full_name,
                    'registered_on' => $p->registered_on?->toDateString(),
                ]),

            // Consumables at or below their reorder level.
            'lowStock' => StockItem::active()
                ->get(['id', 'name', 'unit', 'reorder_level'])
                ->map(fn (StockItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'unit' => $i->unit,
                    'on_hand' => $i->on_hand,
                    'reorder_level' => $i->reorder_level,
                ])
                ->filter(fn ($i) => $i['reorder_level'] > 0 && $i['on_hand'] <= $i['reorder_level'])
                ->values(),
        ]);
    }

    private function outstandingTotal(): int
    {
        $billed = (int) Treatment::sum('amount');
        $settled = (int) Payment::sum(DB::raw('amount + discount'));

        return max(0, $billed - $settled);
    }

    /** @return list<array{date: string, total: int}> */
    private function incomeSeries(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Payment::query()
            ->where('paid_on', '>=', $from->toDateString())
            ->selectRaw('paid_on, SUM(amount) AS total')
            ->groupBy('paid_on')
            ->pluck('total', 'paid_on');

        $series = [];

        // Fill gaps so the chart shows an unbroken axis, not just busy days.
        for ($d = 0; $d < $days; $d++) {
            $date = $from->copy()->addDays($d)->toDateString();
            $series[] = ['date' => $date, 'total' => (int) ($rows[$date] ?? 0)];
        }

        return $series;
    }

    /** @return list<array{name: string, count: int, total: int}> */
    private function topServices(string $since): array
    {
        return Treatment::query()
            ->where('performed_on', '>=', $since)
            ->selectRaw('treatment_service_id, COUNT(*) AS count, SUM(amount) AS total')
            ->groupBy('treatment_service_id')
            ->orderByDesc('count')
            ->limit(6)
            ->with('service:id,name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->service?->name ?? 'نامشخص',
                'count' => (int) $row->count,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
