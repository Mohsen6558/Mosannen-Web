<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\TreatmentService;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['permission:appointments.view'];
    }

    public function index(Request $request): Response
    {
        $date = $request->query('date') ?: now()->toDateString();

        return Inertia::render('Appointments/Index', [
            'date' => $date,
            'appointments' => Appointment::onDate($date)
                ->with(['patient:id,code,first_name,last_name,mobile', 'user:id,full_name,name', 'service:id,name'])
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Appointment $a) => [
                    'id' => $a->id,
                    'starts_at' => substr((string) $a->starts_at, 0, 5),
                    'duration_minutes' => $a->duration_minutes,
                    'status' => $a->status,
                    'notes' => $a->notes,
                    'service' => $a->service?->name,
                    'doctor' => $a->user?->display_name,
                    'patient' => [
                        'id' => $a->patient?->id,
                        'code' => $a->patient?->code,
                        'name' => $a->patient?->full_name,
                        'mobile' => $a->patient?->mobile,
                    ],
                ]),
            'doctors' => User::where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'name']),
            'services' => TreatmentService::active()->ordered()->get(['id', 'name']),
            'statuses' => Appointment::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.manage'), 403);

        $appointment = Appointment::create($this->validated($request));
        ActivityLogger::record('created', $appointment, 'ثبت نوبت');

        return back()->with('success', 'نوبت ثبت شد.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.manage'), 403);

        $appointment->update($this->validated($request));

        return back()->with('success', 'نوبت به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.manage'), 403);

        $appointment->delete();

        return back()->with('success', 'نوبت حذف شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'treatment_service_id' => ['nullable', 'exists:treatment_services,id'],
            'scheduled_on' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'status' => ['required', Rule::in(Appointment::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'patient_id' => 'بیمار',
            'scheduled_on' => 'تاریخ',
            'starts_at' => 'ساعت',
            'duration_minutes' => 'مدت',
        ]);
    }
}
