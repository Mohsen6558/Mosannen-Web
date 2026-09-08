<?php

namespace App\Http\Controllers;

use App\Http\Requests\TreatmentRequest;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentService;
use App\Services\ActivityLogger;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TreatmentController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Treatment::class);

        $treatments = Treatment::query()
            ->with([
                'patient:id,code,first_name,last_name',
                'service:id,name,treatment_category_id',
                'service.category:id,name',
                'user:id,full_name,name',
                'teeth:id,treatment_id,tooth_code',
            ])
            ->between($request->query('from'), $request->query('to'))
            ->when($request->query('service'), fn ($q, $v) => $q->where('treatment_service_id', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->whereHas('service', fn ($s) => $s->where('treatment_category_id', $v)))
            ->when($request->query('patient'), fn ($q, $v) => $q->where('patient_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->whereHas('patient', fn ($p) => $p->search($v)))
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Treatment $t) => [
                'id' => $t->id,
                'performed_on' => $t->performed_on?->toDateString(),
                'patient' => [
                    'id' => $t->patient?->id,
                    'code' => $t->patient?->code,
                    'name' => $t->patient?->full_name,
                ],
                'service' => $t->service?->name,
                'category' => $t->service?->category?->name,
                'teeth' => $t->teeth->pluck('tooth_code'),
                'amount' => (int) $t->amount,
                'user' => $t->user?->display_name,
                'description' => $t->description,
            ]);

        return Inertia::render('Treatments/Index', [
            'treatments' => $treatments,
            'filters' => $request->only('from', 'to', 'service', 'category', 'patient', 'q'),
            'categories' => TreatmentCategory::active()->ordered()->get(['id', 'name']),
            'services' => TreatmentService::active()->ordered()->get(['id', 'name', 'price', 'treatment_category_id']),
            'totals' => [
                'amount' => (int) Treatment::query()
                    ->between($request->query('from'), $request->query('to'))
                    ->when($request->query('patient'), fn ($q, $v) => $q->where('patient_id', $v))
                    ->sum('amount'),
            ],
        ]);
    }

    public function store(TreatmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $treatment = DB::transaction(function () use ($data, $request) {
            $treatment = Treatment::create([
                'patient_id' => $data['patient_id'],
                'treatment_service_id' => $data['treatment_service_id'],
                'user_id' => $request->user()->id,
                'performed_on' => $data['performed_on'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
            ]);

            $treatment->syncTeeth($data['teeth'] ?? []);

            ActivityLogger::record('created', $treatment, 'ثبت درمان', [
                'patient_id' => $treatment->patient_id,
                'amount' => $treatment->amount,
            ]);

            return $treatment;
        });

        if ($request->boolean('send_sms')) {
            $this->sms->treatmentRecorded($treatment->fresh(['patient', 'service']));
        }

        return back()->with('success', 'درمان ثبت شد.');
    }

    public function update(TreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $data = $request->validated();
        $before = $treatment->only(['amount', 'performed_on', 'treatment_service_id']);

        DB::transaction(function () use ($treatment, $data) {
            $treatment->update([
                'patient_id' => $data['patient_id'],
                'treatment_service_id' => $data['treatment_service_id'],
                'performed_on' => $data['performed_on'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
            ]);

            $treatment->syncTeeth($data['teeth'] ?? []);
        });

        ActivityLogger::record('updated', $treatment, 'ویرایش درمان', ['before' => $before]);

        return back()->with('success', 'درمان به‌روزرسانی شد.');
    }

    public function destroy(Treatment $treatment): RedirectResponse
    {
        $this->authorize('delete', $treatment);

        $treatment->delete();
        ActivityLogger::record('deleted', $treatment, 'حذف درمان');

        return back()->with('success', 'درمان حذف شد.');
    }

    /** Tariff lookup used when the operator picks a service on the form. */
    public function servicePrice(TreatmentService $service)
    {
        $this->authorize('viewAny', Treatment::class);

        return ['price' => (int) $service->price];
    }
}
