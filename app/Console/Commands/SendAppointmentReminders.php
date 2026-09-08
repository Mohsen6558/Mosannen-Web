<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use App\Support\JalaliDate;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'clinic:appointment-reminders {--days=1 : How many days ahead to remind}';

    protected $description = 'Send an SMS reminder for upcoming appointments';

    public function handle(SmsService $sms): int
    {
        if (! config('clinic.sms.enabled_events.appointment_reminder')) {
            $this->line('یادآوری نوبت غیرفعال است.');

            return self::SUCCESS;
        }

        $date = now()->addDays((int) $this->option('days'))->toDateString();

        $appointments = Appointment::query()
            ->whereDate('scheduled_on', $date)
            ->whereIn('status', ['scheduled', 'confirmed'])
            // Never remind twice for the same appointment.
            ->whereNull('reminder_sent_at')
            ->with('patient')
            ->get();

        $template = SmsTemplate::for('appointment_reminder');
        $sent = 0;

        foreach ($appointments as $appointment) {
            $patient = $appointment->patient;

            if (! $patient?->mobile) {
                continue;
            }

            $data = [
                'patient' => $patient->full_name,
                'date' => JalaliDate::long($appointment->scheduled_on),
                'time' => JalaliDate::toPersianDigits(substr((string) $appointment->starts_at, 0, 5)),
                'clinic' => config('clinic.name'),
                'phone' => config('clinic.phone'),
            ];

            $body = $template
                ? $template->render($data)
                : "{$data['patient']} عزیز، نوبت شما {$data['date']} ساعت {$data['time']} است.\n{$data['clinic']}";

            if ($sms->queue($patient->mobile, $body, 'appointment', $patient)) {
                $appointment->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            }
        }

        $this->info("{$sent} یادآوری در صف ارسال قرار گرفت.");

        return self::SUCCESS;
    }
}
