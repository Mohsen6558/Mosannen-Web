<?php

namespace App\Http\Requests;

use App\Rules\IranNationalCode;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->patient ? 'patients.update' : 'patients.create');
    }

    /** Normalise digits before validation so Persian input passes numeric rules. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'national_code' => $this->normalizeDigits($this->input('national_code')),
            'mobile' => $this->normalizeMobile($this->input('mobile')),
            'home_phone' => $this->normalizeDigits($this->input('home_phone')),
            'work_phone' => $this->normalizeDigits($this->input('work_phone')),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('patient')?->id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'registered_on' => ['required', 'date'],
            'gender' => ['nullable', Rule::in(['m', 'f'])],
            'national_code' => ['nullable', 'digits:10', new IranNationalCode, Rule::unique('patients')->ignore($id)->whereNull('deleted_at')],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/'],
            'home_phone' => ['nullable', 'string', 'max:20'],
            'work_phone' => ['nullable', 'string', 'max:20'],
            'home_address' => ['nullable', 'string', 'max:255'],
            'work_address' => ['nullable', 'string', 'max:255'],
            'job' => ['nullable', 'string', 'max:255'],
            'referrer_name' => ['nullable', 'string', 'max:255'],
            'binder_code' => ['nullable', 'string', 'max:30'],
            'medical_summary' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'insurance_id' => ['nullable', 'exists:insurances,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'birth_date' => 'تاریخ تولد',
            'registered_on' => 'تاریخ ثبت',
            'gender' => 'جنسیت',
            'national_code' => 'کد ملی',
            'mobile' => 'موبایل',
            'home_phone' => 'تلفن منزل',
            'work_phone' => 'تلفن محل کار',
            'insurance_id' => 'بیمه',
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
            'national_code.digits' => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
            'national_code.unique' => 'این کد ملی قبلاً برای بیمار دیگری ثبت شده است.',
        ];
    }

    private function normalizeDigits(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/\D/', '', JalaliDate::toEnglishDigits((string) $value)) ?: null;
    }

    private function normalizeMobile(mixed $value): ?string
    {
        $v = $this->normalizeDigits($value);

        if (! $v) {
            return null;
        }

        if (str_starts_with($v, '0098')) {
            $v = '0'.substr($v, 4);
        } elseif (str_starts_with($v, '98') && strlen($v) === 12) {
            $v = '0'.substr($v, 2);
        } elseif (strlen($v) === 10 && str_starts_with($v, '9')) {
            $v = '0'.$v;
        }

        return $v;
    }
}
