<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\DrugVariant;
use App\Models\Insurance;
use App\Models\PaymentType;
use App\Models\TreatmentCategory;
use App\Models\TreatmentService;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base-data screens. These replace the legacy frmBimeh / frmPayType /
 * frmTitle / frmSubTitle / frmDrag / frmSubDrag forms, which were six
 * near-identical WinForms with the same CRUD copied into each.
 */
class CatalogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['permission:catalog.manage'];
    }

    // ── Treatment catalogue ──────────────────────────────────────────────

    public function treatments(): Response
    {
        return Inertia::render('Catalog/Treatments', [
            'categories' => TreatmentCategory::ordered()
                ->withCount('services')
                ->get(['id', 'name', 'item_order', 'is_active']),
            'services' => TreatmentService::ordered()
                ->get(['id', 'treatment_category_id', 'name', 'price', 'item_order', 'is_active']),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validateNamed($request);
        $this->created(TreatmentCategory::create($data), 'گروه درمان');

        return back()->with('success', 'گروه درمان ثبت شد.');
    }

    public function updateCategory(Request $request, TreatmentCategory $category): RedirectResponse
    {
        $category->update($this->validateNamed($request));

        return back()->with('success', 'گروه درمان به‌روزرسانی شد.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'treatment_category_id' => ['required', 'exists:treatment_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'item_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'عنوان', 'price' => 'تعرفه']);

        $this->created(TreatmentService::create($data), 'خدمت');

        return back()->with('success', 'خدمت ثبت شد.');
    }

    public function updateService(Request $request, TreatmentService $service): RedirectResponse
    {
        $before = $service->only('price');

        $service->update($request->validate([
            'treatment_category_id' => ['required', 'exists:treatment_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'item_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]));

        // Tariff changes are worth an audit line of their own.
        if ($before['price'] !== $service->price) {
            ActivityLogger::record('updated', $service, 'تغییر تعرفه', [
                'from' => $before['price'],
                'to' => $service->price,
            ]);
        }

        return back()->with('success', 'خدمت به‌روزرسانی شد.');
    }

    // ── Drugs ────────────────────────────────────────────────────────────

    public function drugs(): Response
    {
        return Inertia::render('Catalog/Drugs', [
            'drugs' => Drug::ordered()->withCount('variants')->get(['id', 'name', 'item_order', 'is_active']),
            'variants' => DrugVariant::ordered()->get([
                'id', 'drug_id', 'name', 'default_dosage', 'default_instructions', 'item_order', 'is_active',
            ]),
        ]);
    }

    public function storeDrug(Request $request): RedirectResponse
    {
        $this->created(Drug::create($this->validateNamed($request)), 'دارو');

        return back()->with('success', 'دارو ثبت شد.');
    }

    public function updateDrug(Request $request, Drug $drug): RedirectResponse
    {
        $drug->update($this->validateNamed($request));

        return back()->with('success', 'دارو به‌روزرسانی شد.');
    }

    public function storeVariant(Request $request): RedirectResponse
    {
        $this->created(DrugVariant::create($this->validateVariant($request)), 'زیردارو');

        return back()->with('success', 'زیردارو ثبت شد.');
    }

    public function updateVariant(Request $request, DrugVariant $variant): RedirectResponse
    {
        $variant->update($this->validateVariant($request));

        return back()->with('success', 'زیردارو به‌روزرسانی شد.');
    }

    // ── Insurances and payment methods ───────────────────────────────────

    public function insurances(): Response
    {
        return Inertia::render('Catalog/Insurances', [
            'insurances' => Insurance::ordered()->withCount('patients')->get(['id', 'name', 'item_order', 'is_active']),
        ]);
    }

    public function storeInsurance(Request $request): RedirectResponse
    {
        $this->created(Insurance::create($this->validateNamed($request)), 'بیمه');

        return back()->with('success', 'بیمه ثبت شد.');
    }

    public function updateInsurance(Request $request, Insurance $insurance): RedirectResponse
    {
        $insurance->update($this->validateNamed($request));

        return back()->with('success', 'بیمه به‌روزرسانی شد.');
    }

    public function paymentTypes(): Response
    {
        return Inertia::render('Catalog/PaymentTypes', [
            'paymentTypes' => PaymentType::ordered()->withCount('payments')->get(['id', 'name', 'item_order', 'is_active']),
        ]);
    }

    public function storePaymentType(Request $request): RedirectResponse
    {
        $this->created(PaymentType::create($this->validateNamed($request)), 'نحوه پرداخت');

        return back()->with('success', 'نحوه پرداخت ثبت شد.');
    }

    public function updatePaymentType(Request $request, PaymentType $paymentType): RedirectResponse
    {
        $paymentType->update($this->validateNamed($request));

        return back()->with('success', 'نحوه پرداخت به‌روزرسانی شد.');
    }

    // ── Shared validation ────────────────────────────────────────────────

    private function validateNamed(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'item_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'عنوان', 'item_order' => 'ترتیب']);
    }

    private function validateVariant(Request $request): array
    {
        return $request->validate([
            'drug_id' => ['required', 'exists:drugs,id'],
            'name' => ['required', 'string', 'max:255'],
            'default_dosage' => ['nullable', 'string', 'max:255'],
            'default_instructions' => ['nullable', 'string', 'max:255'],
            'item_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'عنوان']);
    }

    private function created(Model $model, string $label): void
    {
        ActivityLogger::record('created', $model, "ثبت {$label}");
    }
}
