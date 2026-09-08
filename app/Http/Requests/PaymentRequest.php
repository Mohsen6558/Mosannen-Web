<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('payment') ? 'payments.update' : 'payments.create');
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'payment_type_id' => ['nullable', 'exists:payment_types,id'],
            // Back-dating is a separate permission, enforced in the controller.
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'discount' => ['integer', 'min:0', 'max:99999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'send_sms' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ((int) $this->input('amount') === 0 && (int) $this->input('discount') === 0) {
                    $validator->errors()->add('amount', 'مبلغ و تخفیف نمی‌توانند هر دو صفر باشند.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'بیمار',
            'payment_type_id' => 'نحوه پرداخت',
            'paid_on' => 'تاریخ پرداخت',
            'amount' => 'مبلغ',
            'discount' => 'تخفیف',
        ];
    }
}
