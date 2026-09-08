<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\DrugVariant;
use App\Models\Prescription;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['permission:prescriptions.view'];
    }

    public function index(Request $request): Response
    {
        $prescriptions = Prescription::query()
            ->with(['patient:id,code,first_name,last_name', 'user:id,full_name,name'])
            ->withCount('items')
            ->when($request->query('patient'), fn ($q, $v) => $q->where('patient_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->whereHas('patient', fn ($p) => $p->search($v)))
            ->orderByDesc('prescribed_on')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Prescription $p) => [
                'id' => $p->id,
                'prescribed_on' => $p->prescribed_on?->toDateString(),
                'patient' => [
                    'id' => $p->patient?->id,
                    'code' => $p->patient?->code,
                    'name' => $p->patient?->full_name,
                ],
                'items_count' => $p->items_count,
                'user' => $p->user?->display_name,
                'notes' => $p->notes,
            ]);

        return Inertia::render('Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'filters' => $request->only('patient', 'q'),
            'drugs' => Drug::active()->ordered()->get(['id', 'name']),
            'variants' => DrugVariant::active()->ordered()
                ->get(['id', 'drug_id', 'name', 'default_dosage', 'default_instructions']),
        ]);
    }

    public function show(Prescription $prescription): Response
    {
        $prescription->load(['patient', 'user', 'items.variant.drug']);

        return Inertia::render('Prescriptions/Show', [
            'prescription' => [
                'id' => $prescription->id,
                'prescribed_on' => $prescription->prescribed_on?->toDateString(),
                'notes' => $prescription->notes,
                'user' => $prescription->user?->display_name,
                'patient' => [
                    'id' => $prescription->patient?->id,
                    'code' => $prescription->patient?->code,
                    'name' => $prescription->patient?->full_name,
                ],
                'items' => $prescription->items->map(fn ($i) => [
                    'id' => $i->id,
                    'drug' => $i->variant?->drug?->name,
                    'variant' => $i->variant?->name,
                    'dosage' => $i->dosage,
                    'instructions' => $i->instructions,
                    'quantity' => $i->quantity,
                ]),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('prescriptions.manage'), 403);

        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'prescribed_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.drug_variant_id' => ['required', 'exists:drug_variants,id'],
            'items.*.dosage' => ['nullable', 'string', 'max:255'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ], [], [
            'patient_id' => 'بیمار',
            'prescribed_on' => 'تاریخ نسخه',
            'items' => 'اقلام نسخه',
        ]);

        $prescription = DB::transaction(function () use ($data, $request) {
            $prescription = Prescription::create([
                'patient_id' => $data['patient_id'],
                'user_id' => $request->user()->id,
                'prescribed_on' => $data['prescribed_on'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach (array_values($data['items']) as $index => $item) {
                $prescription->items()->create([...$item, 'item_order' => $index]);
            }

            return $prescription;
        });

        ActivityLogger::record('created', $prescription, 'ثبت نسخه');

        return redirect()->route('prescriptions.show', $prescription)
            ->with('success', 'نسخه ثبت شد.');
    }

    public function destroy(Request $request, Prescription $prescription): RedirectResponse
    {
        abort_unless($request->user()->can('prescriptions.manage'), 403);

        $prescription->delete();
        ActivityLogger::record('deleted', $prescription, 'حذف نسخه');

        return redirect()->route('prescriptions.index')->with('success', 'نسخه حذف شد.');
    }
}
