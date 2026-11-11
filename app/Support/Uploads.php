<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Uploads
{
    public static function diskName(): string
    {
        return (string) config('filesystems.upload_disk', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::diskName());
    }

    public static function exists(?string $path): bool
    {
        return filled($path) && self::disk()->exists($path);
    }

    public static function delete(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        return self::disk()->delete($path);
    }

    public static function mime(?string $path): string
    {
        if (! filled($path)) {
            return 'application/octet-stream';
        }

        return self::disk()->mimeType($path) ?: 'application/octet-stream';
    }

    public static function inline(string $path, ?string $filename = null): StreamedResponse
    {
        $name = $filename ?: basename($path);

        return self::disk()->response($path, $name, [
            'Content-Type' => self::mime($path),
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public static function download(string $path, ?string $filename = null): StreamedResponse
    {
        return self::disk()->download($path, $filename ?: basename($path));
    }
}
