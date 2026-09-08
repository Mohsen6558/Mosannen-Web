<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Models\Insurance;
use App\Models\Patient;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    private const SORTABLE = ['code', 'last_name', 'registered_on', 'created_at'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);

        $sortBy = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';
        $sortDir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $patients = Patient::query()
            ->with('insurance:id,name')
            ->withBalance()
            ->search($request->query('q'))
            ->when($request->query('insurance'), fn ($q, $v) => $q->where('insurance_id', $v))
            // Files that still owe money. Correlated subqueries rather than
            // HAVING, so the filter composes with ORDER BY and pagination.
            ->when($request->query('debtors') === '1', fn ($q) => $q->whereRaw(
                '(SELECT COALESCE(SUM(amount), 0) FROM treatments
                    WHERE treatments.patient_id = patients.id AND treatments.deleted_at IS NULL)
                 > (SELECT COALESCE(SUM(amount + discount), 0) FROM payments
                    WHERE payments.patient_id = patients.id AND payments.deleted_at IS NULL)'
            ))
            ->orderBy($sortBy, $sortDir)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'full_name' => $p->full_name,
                'mobile' => $p->mobile,
                'national_code' => $p->national_code,
                'insurance' => $p->insurance?->name,
                'registered_on' => $p->registered_on?->toDateString(),
                'balance' => (int) ($p->billed_total ?? 0)
                    - (int) ($p->paid_total ?? 0)
                    - (int) ($p->discount_total ?? 0),
            ]);

        return Inertia::render('Patients/Index', [
            'patients' => $patients,
            'filters' => $request->only('q', 'insurance', 'debtors', 'sort', 'dir'),
            'insurances' => Insurance::active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Patients/Form', [
            'patient' => null,
            'nextCode' => Patient::nextCode(),
            'insurances' => Insurance::active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function store(PatientRequest $request): RedirectResponse
    {
        $patient = DB::transaction(function () use ($request) {
            $patient = Patient::create([
                ...$request->validated(),
                'code' => Patient::nextCode(),
                'created_by' => $request->user()->id,
            ]);

            ActivityLogger::record('created', $patient, 'ثبت بیمار جدید');

            return $patient;
        });

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', "پرونده {$patient->full_name} ایجاد شد.");
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        $patient->load([
            'insurance:id,name',
            'creator:id,full_name,name',
        ]);

        return Inertia::render('Patients/Show', [
            'patient' => [
                ...$patient->only([
                    'id', 'code', 'first_name', 'last_name', 'father_name', 'gender',
                    'national_code', 'mobile', 'home_phone', 'work_phone',
                    'home_address', 'work_address', 'job', 'referrer_name',
                    'binder_code', 'medical_summary', 'description', 'notes',
                ]),
                'full_name' => $patient->full_name,
                'birth_date' => $patient->birth_date?->toDateString(),
                'registered_on' => $patient->registered_on?->toDateString(),
                'insurance' => $patient->insurance,
                'created_by' => $patient->creator?->display_name,
            ],

            'summary' => $this->summaryFor($patient),

            'treatments' => $patient->treatments()
                ->with(['service:id,name,treatment_category_id', 'service.category:id,name', 'user:id,full_name,name', 'teeth:id,treatment_id,tooth_code'])
                ->orderByDesc('performed_on')->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'performed_on' => $t->performed_on?->toDateString(),
                    'service' => $t->service?->name,
                    'category' => $t->service?->category?->name,
                    'amount' => (int) $t->amount,
                    'teeth' => $t->teeth->pluck('tooth_code'),
                    'user' => $t->user?->display_name,
                    'description' => $t->description,
                ]),

            'payments' => $patient->payments()
                ->with(['paymentType:id,name', 'user:id,full_name,name'])
                ->orderByDesc('paid_on')->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'paid_on' => $p->paid_on?->toDateString(),
                    'paid_at' => $p->paid_at,
                    'amount' => (int) $p->amount,
                    'discount' => (int) $p->discount,
                    'type' => $p->paymentType?->name,
                    'user' => $p->user?->display_name,
                    'description' => $p->description,
                ]),

            'radiographs' => $patient->radiographs()
                ->orderByDesc('taken_on')->limit(60)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'taken_on' => $r->taken_on?->toDateString(),
                    'subject' => $r->subject,
                    'has_file' => (bool) $r->path,
                    'url' => $r->path ? route('images.show', $r) : null,
                    'thumb' => $r->path ? route('images.thumbnail', $r) : null,
                ]),

            'appointments' => $patient->appointments()
                ->upcoming()->orderBy('scheduled_on')->orderBy('starts_at')
                ->limit(10)
                ->get(['id', 'scheduled_on', 'starts_at', 'status', 'notes']),

            'prescriptions' => $patient->prescriptions()
                ->withCount('items')
                ->orderByDesc('prescribed_on')->limit(20)
                ->get(['id', 'prescribed_on', 'notes']),
        ]);
    }

    public function edit(Patient $patient): Response
    {
        $this->authorize('update', $patient);

        return Inertia::render('Patients/Form', [
            'patient' => [
                ...$patient->only([
                    'id', 'code', 'first_name', 'last_name', 'father_name', 'gender',
                    'national_code', 'mobile', 'home_phone', 'work_phone',
                    'home_address', 'work_address', 'job', 'referrer_name',
                    'binder_code', 'medical_summary', 'description', 'notes', 'insurance_id',
                ]),
                'birth_date' => $patient->birth_date?->toDateString(),
                'registered_on' => $patient->registered_on?->toDateString(),
            ],
            'nextCode' => null,
            'insurances' => Insurance::active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        $before = $patient->getOriginal();
        $patient->update($request->validated());

        ActivityLogger::record('updated', $patient, 'ویرایش مشخصات بیمار', [
            'changed' => array_keys($patient->getChanges()),
            'before' => array_intersect_key($before, $patient->getChanges()),
        ]);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'مشخصات بیمار به‌روزرسانی شد.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);

        // Soft delete only: clinical and financial history must remain
        // reachable for audit even after a file is closed.
        $patient->delete();

        ActivityLogger::record('deleted', $patient, 'حذف پرونده بیمار');

        return redirect()
            ->route('patients.index')
            ->with('success', 'پرونده بیمار حذف شد.');
    }

    /** Live search used by the treatment and payment forms. */
    public function search(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        return Patient::query()
            ->search($request->query('q'))
            ->orderBy('last_name')
            ->limit(15)
            ->get(['id', 'code', 'first_name', 'last_name', 'mobile'])
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'label' => $p->full_name,
                'mobile' => $p->mobile,
            ]);
    }

    /** @return array<string, int> */
    private function summaryFor(Patient $patient): array
    {
        $billed = (int) $patient->treatments()->sum('amount');
        $paid = (int) $patient->payments()->sum('amount');
        $discount = (int) $patient->payments()->sum('discount');

        return [
            'billed' => $billed,
            'paid' => $paid,
            'discount' => $discount,
            'balance' => $billed - $paid - $discount,
            'treatment_count' => $patient->treatments()->count(),
            'radiograph_count' => $patient->radiographs()->count(),
        ];
    }
}
