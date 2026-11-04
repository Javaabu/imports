<?php

namespace Javaabu\Imports\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Javaabu\Imports\Enums\Formats;
use RuntimeException;

/**
 * Local files: copies of uploads being read, and reports being written, before they move to a disk
 */
class TemporaryFile
{
    public static function path(Formats|string $extension, string $prefix = 'import'): string
    {
        $directory = rtrim(config('imports.temporary_directory') ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = $extension instanceof Formats ? $extension->extension() : $extension;

        return $directory . DIRECTORY_SEPARATOR . $prefix . '-' . Str::random(32) . '.' . $extension;
    }

    /**
     * Copy a file from a disk to a local temporary file, streaming it
     */
    public static function fromDisk(?string $disk, string $path, Formats|string $extension): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Could not read {$path}.");
        }

        $local_path = static::path($extension);
        $local = fopen($local_path, 'w');

        try {
            stream_copy_to_stream($stream, $local);
        } finally {
            fclose($local);
            fclose($stream);
        }

        return $local_path;
    }

    /**
     * Stream a local file to a disk (the local file is kept)
     *
     * @return int|null the size of the file in bytes
     */
    public static function copyToDisk(string $local_path, string $path, ?string $disk): ?int
    {
        $size = filesize($local_path) ?: null;
        $stream = fopen($local_path, 'r');

        try {
            $stored = Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($stored === false) {
            throw new RuntimeException("Could not write {$path}.");
        }

        return $size;
    }

    public static function delete(?string $path): void
    {
        if ($path && is_file($path)) {
            unlink($path);
        }
    }
}
