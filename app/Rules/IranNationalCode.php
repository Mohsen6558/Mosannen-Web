<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Iranian national code (کد ملی) checksum.
 *
 * Ten digits, where the last is a check digit over the first nine:
 * sum(d[i] × (10 − i)) mod 11 — the remainder is the check digit when it is
 * below 2, otherwise 11 minus the remainder. Repdigits such as 1111111111
 * satisfy the arithmetic but are never issued, so they are rejected too.
 */
class IranNationalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = preg_replace('/\D/', '', (string) $value);

        if ($code === '' || $code === null) {
            return; // "nullable" governs emptiness; this rule only judges format.
        }

        if (strlen($code) !== 10 || preg_match('/^(\d)\1{9}$/', $code)) {
            $fail('کد ملی وارد شده معتبر نیست.');

            return;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        $valid = $remainder < 2
            ? $check === $remainder
            : $check === 11 - $remainder;

        if (! $valid) {
            $fail('کد ملی وارد شده معتبر نیست.');
        }
    }
}
