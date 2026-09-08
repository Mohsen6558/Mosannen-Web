<?php

namespace App\Http\Requests;

use App\Support\Teeth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('treatment') ? 'treatments.update' : 'treatments.create');
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'treatment_service_id' => ['required', 'exists:treatment_services,id'],
            'performed_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'teeth' => ['array', 'max:52'],
            'teeth.*' => ['string', Rule::in(Teeth::all())],
            'send_sms' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'بیمار',
            'treatment_service_id' => 'نوع درمان',
            'performed_on' => 'تاریخ درمان',
            'amount' => 'مبلغ',
            'teeth' => 'دندان‌ها',
        ];
    }

    public function messages(): array
    {
        return [
            'performed_on.before_or_equal' => 'تاریخ درمان نمی‌تواند در آینده باشد.',
            'teeth.*.in' => 'کد دندان انتخاب‌شده معتبر نیست.',
        ];
    }
}
