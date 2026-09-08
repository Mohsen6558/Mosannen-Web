<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Services\ActivityLogger;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $base = fn () => Payment::query()
            ->between($request->query('from'), $request->query('to'))
            ->when($request->query('type'), fn ($q, $v) => $q->where('payment_type_id', $v))
            ->when($request->query('patient'), fn ($q, $v) => $q->where('patient_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->whereHas('patient', fn ($p) => $p->search($v)));

        $payments = $base()
            ->with(['patient:id,code,first_name,last_name', 'paymentType:id,name', 'user:id,full_name,name'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'paid_on' => $p->paid_on?->toDateString(),
                'paid_at' => $p->paid_at,
                'patient' => [
                    'id' => $p->patient?->id,
                    'code' => $p->patient?->code,
                    'name' => $p->patient?->full_name,
                ],
                'type' => $p->paymentType?->name,
                'amount' => (int) $p->amount,
                'discount' => (int) $p->discount,
                'user' => $p->user?->display_name,
                'description' => $p->description,
            ]);

        // Per-method breakdown for the till reconciliation at end of day.
        $byType = $base()
            ->selectRaw('payment_type_id, SUM(amount) AS total, COUNT(*) AS count')
            ->groupBy('payment_type_id')
            ->with('paymentType:id,name')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->paymentType?->name ?? 'نامشخص',
                'total' => (int) $row->total,
                'count' => (int) $row->count,
            ]);

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only('from', 'to', 'type', 'patient', 'q'),
            'paymentTypes' => PaymentType::active()->ordered()->get(['id', 'name']),
            'totals' => [
                'amount' => (int) $base()->sum('amount'),
                'discount' => (int) $base()->sum('discount'),
                'count' => $base()->count(),
                'by_type' => $byType,
            ],
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->guardBackdating($request, $data['paid_on']);
        $this->guardDiscount($request, (int) ($data['discount'] ?? 0));

        $payment = DB::transaction(function () use ($data, $request) {
            $payment = Payment::create([
                'patient_id' => $data['patient_id'],
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'user_id' => $request->user()->id,
                'paid_on' => $data['paid_on'],
                'paid_at' => now()->format('H:i'),
                'amount' => $data['amount'],
                'discount' => $data['discount'] ?? 0,
                'description' => $data['description'] ?? null,
            ]);

            ActivityLogger::record('created', $payment, 'ثبت پرداخت', [
                'patient_id' => $payment->patient_id,
                'amount' => $payment->amount,
                'discount' => $payment->discount,
            ]);

            return $payment;
        });

        if ($request->boolean('send_sms')) {
            $this->sms->paymentReceived($payment->fresh('patient'));
        }

        return back()->with('success', 'پرداخت ثبت شد.');
    }

    public function update(PaymentRequest $request, Payment $payment): RedirectResponse
    {
        $data = $request->validated();

        if ($data['paid_on'] !== $payment->paid_on?->toDateString()) {
            $this->guardBackdating($request, $data['paid_on'], force: true);
        }

        $this->guardDiscount($request, (int) ($data['discount'] ?? 0));

        $before = $payment->only(['amount', 'discount', 'paid_on', 'payment_type_id']);

        $payment->update([
            'patient_id' => $data['patient_id'],
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'paid_on' => $data['paid_on'],
            'amount' => $data['amount'],
            'discount' => $data['discount'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        ActivityLogger::record('updated', $payment, 'ویرایش پرداخت', ['before' => $before]);

        return back()->with('success', 'پرداخت به‌روزرسانی شد.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $payment->delete();
        ActivityLogger::record('deleted', $payment, 'حذف پرداخت', [
            'amount' => $payment->amount,
            'patient_id' => $payment->patient_id,
        ]);

        return back()->with('success', 'پرداخت حذف شد.');
    }

    /**
     * Recording a receipt on a day other than today needs its own permission —
     * the same distinction the legacy app drew with flag 9.
     */
    private function guardBackdating(Request $request, string $paidOn, bool $force = false): void
    {
        if (! $force && $paidOn === now()->toDateString()) {
            return;
        }

        if ($paidOn !== now()->toDateString() || $force) {
            $this->authorize('changeDate', Payment::class);
        }
    }

    private function guardDiscount(Request $request, int $discount): void
    {
        if ($discount > 0) {
            $this->authorize('applyDiscount', Payment::class);
        }
    }
}
