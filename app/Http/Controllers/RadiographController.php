<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Radiograph;
use App\Services\ActivityLogger;
use App\Services\ImageService;
use App\Support\Teeth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RadiographController extends Controller
{
    public function __construct(private readonly ImageService $images) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Radiograph::class);

        $radiographs = Radiograph::query()
            ->with('patient:id,code,first_name,last_name')
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('taken_on', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('taken_on', '<=', $v))
            ->when($request->query('patient'), fn ($q, $v) => $q->where('patient_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->whereHas('patient', fn ($p) => $p->search($v)))
            ->orderByDesc('taken_on')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Radiograph $r) => [
                'id' => $r->id,
                'taken_on' => $r->taken_on?->toDateString(),
                'subject' => $r->subject,
                'patient' => [
                    'id' => $r->patient?->id,
                    'code' => $r->patient?->code,
                    'name' => $r->patient?->full_name,
                ],
                'has_file' => (bool) $r->path,
                'url' => $r->path ? route('images.show', $r) : null,
                'thumb' => $r->path ? route('images.thumbnail', $r) : null,
            ]);

        return Inertia::render('Images/Index', [
            'radiographs' => $radiographs,
            'filters' => $request->only('from', 'to', 'patient', 'q'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Radiograph::class);

        $maxKb = (int) config('clinic.images.max_upload_mb', 25) * 1024;

        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'taken_on' => ['required', 'date', 'before_or_equal:today'],
            'subject' => ['nullable', 'string', 'max:255'],
            'teeth' => ['array', 'max:52'],
            'teeth.*' => ['string', Rule::in(Teeth::all())],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,bmp,tif,tiff', "max:{$maxKb}"],
        ], [], [
            'patient_id' => 'بیمار',
            'taken_on' => 'تاریخ عکس‌برداری',
            'files' => 'فایل تصویر',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        $count = 0;

        foreach ($request->file('files') as $file) {
            $attributes = $this->images->store($file, $patient, $data['taken_on']);

            // The same sensor image imported twice is a duplicate, not a
            // second radiograph; skip it rather than storing it again.
            if ($attributes['checksum'] && Radiograph::where('patient_id', $patient->id)
                ->where('checksum', $attributes['checksum'])->exists()) {
                Storage::disk($attributes['disk'])->delete($attributes['path']);

                continue;
            }

            $radiograph = Radiograph::create([
                ...$attributes,
                'patient_id' => $patient->id,
                'user_id' => $request->user()->id,
                'taken_on' => $data['taken_on'],
                'subject' => $data['subject'] ?? null,
            ]);

            $radiograph->syncTeeth($data['teeth'] ?? []);
            $count++;
        }

        ActivityLogger::record('created', $patient, "بارگذاری {$count} تصویر رادیوگرافی");

        return back()->with(
            $count > 0 ? 'success' : 'warning',
            $count > 0 ? "{$count} تصویر بارگذاری شد." : 'تصویر تکراری بود و ذخیره نشد.',
        );
    }

    /** Stream the full image. Never a public URL. */
    public function show(Radiograph $radiograph): StreamedResponse
    {
        $this->authorize('view', $radiograph);
        abort_unless($radiograph->exists(), 404, 'فایل تصویر پیدا نشد.');

        return $radiograph->storage()->response(
            $radiograph->path,
            $radiograph->original_name ?: 'radiograph.jpg',
            ['Content-Type' => $radiograph->mime ?: 'image/jpeg', 'Cache-Control' => 'private, max-age=3600'],
        );
    }

    public function thumbnail(Radiograph $radiograph): StreamedResponse
    {
        $this->authorize('view', $radiograph);

        $path = $this->images->thumbnail($radiograph) ?? $radiograph->path;
        abort_unless($path && $radiograph->storage()->exists($path), 404);

        return $radiograph->storage()->response($path, 'thumb.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function destroy(Radiograph $radiograph): RedirectResponse
    {
        $this->authorize('delete', $radiograph);

        $this->images->delete($radiograph);
        $radiograph->delete();

        ActivityLogger::record('deleted', $radiograph, 'حذف تصویر رادیوگرافی');

        return back()->with('success', 'تصویر حذف شد.');
    }
}
