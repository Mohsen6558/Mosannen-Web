<?php

namespace App\Services;

use App\Jobs\SendSmsMessage;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\Treatment;
use App\Services\Sms\KavenegarDriver;
use App\Services\Sms\LogDriver;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsIrDriver;
use App\Support\JalaliDate;
use Illuminate\Support\Facades\Auth;

/**
 * Queues and delivers patient SMS.
 *
 * Messages are always persisted first and delivered from a job, so a provider
 * outage never blocks the operator who is standing in front of the patient,
 * and every message the clinic sent stays on record.
 */
class SmsService
{
    public function driver(): SmsDriver
    {
        $config = config('clinic.sms');

        return match ($config['driver']) {
            'kavenegar' => new KavenegarDriver($config['kavenegar']['api_key'] ?? '', $config['sender'] ?: null),
            'smsir' => new SmsIrDriver($config['smsir']['api_key'] ?? '', $config['sender'] ?: null),
            default => new LogDriver,
        };
    }

    /** Persist a message and hand it to the queue. */
    public function queue(
        string $mobile,
        string $body,
        string $kind = 'manual',
        ?Patient $patient = null,
    ): ?SmsMessage {
        $mobile = $this->normalize($mobile);

        if (! $mobile || trim($body) === '') {
            return null;
        }

        $message = SmsMessage::create([
            'patient_id' => $patient?->id,
            'user_id' => Auth::id(),
            'mobile' => $mobile,
            'body' => $body,
            'kind' => $kind,
            'status' => 'queued',
        ]);

        SendSmsMessage::dispatch($message->id);

        return $message;
    }

    public function treatmentRecorded(Treatment $treatment): ?SmsMessage
    {
        if (! config('clinic.sms.enabled_events.treatment_created')) {
            return null;
        }

        $patient = $treatment->patient;

        if (! $patient?->mobile) {
            return null;
        }

        return $this->queue(
            $patient->mobile,
            $this->render('treatment_created', [
                'patient' => $patient->full_name,
                'service' => $treatment->service?->name ?? '',
                'date' => JalaliDate::format($treatment->performed_on),
                'amount' => JalaliDate::toPersianDigits(number_format((int) $treatment->amount)),
                'clinic' => config('clinic.name'),
            ], default: 'درمان {{service}} در تاریخ {{date}} برای {{patient}} به مبلغ {{amount}} ریال ثبت شد.'."\n".'{{clinic}}'),
            kind: 'treatment',
            patient: $patient,
        );
    }

    public function paymentReceived(Payment $payment): ?SmsMessage
    {
        if (! config('clinic.sms.enabled_events.payment_created')) {
            return null;
        }

        $patient = $payment->patient;

        if (! $patient?->mobile) {
            return null;
        }

        return $this->queue(
            $patient->mobile,
            $this->render('payment_created', [
                'patient' => $patient->full_name,
                'date' => JalaliDate::format($payment->paid_on),
                'amount' => JalaliDate::toPersianDigits(number_format((int) $payment->amount)),
                'balance' => JalaliDate::toPersianDigits(number_format(max(0, $patient->balance))),
                'clinic' => config('clinic.name'),
            ], default: 'مبلغ {{amount}} ریال در تاریخ {{date}} از {{patient}} دریافت شد. مانده: {{balance}} ریال'."\n".'{{clinic}}'),
            kind: 'payment',
            patient: $patient,
        );
    }

    /** Prefer the staff-editable template; fall back to the shipped wording. */
    private function render(string $key, array $data, string $default): string
    {
        $template = SmsTemplate::for($key);

        if ($template) {
            return $template->render($data);
        }

        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn ($m) => (string) ($data[$m[1]] ?? ''),
            $default,
        );
    }

    private function normalize(?string $value): ?string
    {
        $v = preg_replace('/\D/', '', JalaliDate::toEnglishDigits((string) $value));

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

        return preg_match('/^09\d{9}$/', $v) ? $v : null;
    }
}
