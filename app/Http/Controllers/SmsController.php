<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsMessage;
use App\Models\Patient;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class SmsController extends Controller implements HasMiddleware
{
    public function __construct(private readonly SmsService $sms) {}

    public static function middleware(): array
    {
        return ['permission:sms.view'];
    }

    public function index(Request $request): Response
    {
        $messages = SmsMessage::query()
            ->with('patient:id,code,first_name,last_name')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('mobile', 'like', "%{$v}%")
                ->orWhere('body', 'ilike', "%{$v}%")))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (SmsMessage $m) => [
                'id' => $m->id,
                'mobile' => $m->mobile,
                'body' => $m->body,
                'kind' => $m->kind,
                'status' => $m->status,
                'error' => $m->error,
                'sent_at' => $m->sent_at?->toIso8601String(),
                'created_at' => $m->created_at?->toIso8601String(),
                'patient' => $m->patient ? [
                    'id' => $m->patient->id,
                    'name' => $m->patient->full_name,
                ] : null,
            ]);

        return Inertia::render('Sms/Index', [
            'messages' => $messages,
            'filters' => $request->only('status', 'q'),
            'templates' => SmsTemplate::orderBy('name')->get(['id', 'key', 'name', 'body', 'is_active']),
            'driver' => config('clinic.sms.driver'),
            'counts' => [
                'queued' => SmsMessage::where('status', 'queued')->count(),
                'failed' => SmsMessage::where('status', 'failed')->count(),
            ],
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('sms.send'), 403);

        $data = $request->validate([
            'mobile' => ['required_without:patient_id', 'nullable', 'regex:/^09\d{9}$/'],
            'patient_id' => ['nullable', 'exists:patients,id'],
            'body' => ['required', 'string', 'max:1000'],
        ], [], ['mobile' => 'شماره موبایل', 'body' => 'متن پیام']);

        $patient = isset($data['patient_id']) ? Patient::find($data['patient_id']) : null;
        $mobile = $data['mobile'] ?: $patient?->mobile;

        if (! $mobile) {
            return back()->withErrors(['mobile' => 'برای این بیمار شماره موبایلی ثبت نشده است.']);
        }

        $message = $this->sms->queue($mobile, $data['body'], 'manual', $patient);

        return back()->with(
            $message ? 'success' : 'error',
            $message ? 'پیام در صف ارسال قرار گرفت.' : 'شماره موبایل معتبر نیست.',
        );
    }

    /** Put a failed message back in the queue. */
    public function retry(Request $request, SmsMessage $message): RedirectResponse
    {
        abort_unless($request->user()->can('sms.send'), 403);
        abort_unless($message->status === 'failed', 422, 'فقط پیام‌های ناموفق قابل ارسال مجدد هستند.');

        $message->update(['status' => 'queued', 'error' => null]);
        SendSmsMessage::dispatch($message->id);

        return back()->with('success', 'پیام دوباره در صف ارسال قرار گرفت.');
    }

    public function cancel(Request $request, SmsMessage $message): RedirectResponse
    {
        abort_unless($request->user()->can('sms.send'), 403);
        abort_unless($message->status === 'queued', 422);

        $message->update(['status' => 'cancelled']);

        return back()->with('success', 'ارسال پیام لغو شد.');
    }

    public function updateTemplate(Request $request, SmsTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $template->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ], [], ['body' => 'متن قالب']));

        return back()->with('success', 'قالب پیامک به‌روزرسانی شد.');
    }
}
