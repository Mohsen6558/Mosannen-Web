<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Radiograph;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Radiography file handling.
 *
 * Files are stored under a random path on a private disk and are only ever
 * reachable through an authorised controller — never a public URL, and never
 * under a name derived from the patient.
 */
class ImageService
{
    public function disk(): string
    {
        return config('clinic.images.disk', 'images');
    }

    public function store(UploadedFile $file, Patient $patient, string $takenOn): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = sprintf('radiographs/%s/%s.%s', substr($takenOn, 0, 7), Str::uuid(), $extension);

        Storage::disk($this->disk())->put($path, $file->get(), 'private');

        $attributes = [
            'disk' => $this->disk(),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
        ];

        if ($dimensions = @getimagesize($file->getRealPath())) {
            $attributes['width'] = $dimensions[0];
            $attributes['height'] = $dimensions[1];
        }

        return $attributes;
    }

    /**
     * Build (and cache) a thumbnail. Generated lazily on first view rather
     * than at upload so a bulk import does not pay for images nobody opens.
     */
    public function thumbnail(Radiograph $radiograph): ?string
    {
        if (! $radiograph->path) {
            return null;
        }

        $disk = Storage::disk($radiograph->disk ?: $this->disk());

        if ($radiograph->thumbnail_path && $disk->exists($radiograph->thumbnail_path)) {
            return $radiograph->thumbnail_path;
        }

        if (! $disk->exists($radiograph->path) || ! extension_loaded('gd')) {
            return null;
        }

        $source = @imagecreatefromstring($disk->get($radiograph->path));

        if (! $source) {
            return null;
        }

        $width = config('clinic.images.thumbnail_width', 320);
        $ratio = imagesy($source) / max(1, imagesx($source));
        $thumb = imagescale($source, $width, (int) round($width * $ratio));
        imagedestroy($source);

        if (! $thumb) {
            return null;
        }

        ob_start();
        imagejpeg($thumb, null, 78);
        $bytes = ob_get_clean();
        imagedestroy($thumb);

        $path = preg_replace('/(\.[^.]+)$/', '', $radiograph->path).'_thumb.jpg';
        $disk->put($path, $bytes, 'private');

        $radiograph->forceFill(['thumbnail_path' => $path])->save();

        return $path;
    }

    public function delete(Radiograph $radiograph): void
    {
        $disk = Storage::disk($radiograph->disk ?: $this->disk());

        foreach (array_filter([$radiograph->path, $radiograph->thumbnail_path]) as $path) {
            $disk->delete($path);
        }
    }
}
