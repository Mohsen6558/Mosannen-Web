<?php

namespace App\Http\Controllers;

use App\Models\DrugVariant;
use App\Models\PaymentType;
use App\Models\TreatmentService;
use Illuminate\Http\JsonResponse;

/**
 * Small JSON lookups for forms that open on demand (the treatment and
 * payment modals). Kept out of the page props so a patient record does not
 * carry the whole catalogue on every load.
 */
class LookupController extends Controller
{
    public function services(): JsonResponse
    {
        return response()->json(
            TreatmentService::active()->ordered()
                ->with('category:id,name')
                ->get(['id', 'name', 'price', 'treatment_category_id'])
                ->map(fn (TreatmentService $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'price' => (int) $s->price,
                    'category' => $s->category?->name,
                ]),
        );
    }

    public function paymentTypes(): JsonResponse
    {
        return response()->json(PaymentType::active()->ordered()->get(['id', 'name']));
    }

    public function drugVariants(): JsonResponse
    {
        return response()->json(
            DrugVariant::active()->ordered()
                ->with('drug:id,name')
                ->get(['id', 'drug_id', 'name', 'default_dosage', 'default_instructions'])
                ->map(fn (DrugVariant $v) => [
                    'id' => $v->id,
                    'drug_id' => $v->drug_id,
                    'name' => $v->name,
                    'label' => trim(($v->drug?->name ?? '').' — '.$v->name, ' —'),
                    'default_dosage' => $v->default_dosage,
                    'default_instructions' => $v->default_instructions,
                ]),
        );
    }
}
