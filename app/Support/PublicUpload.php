<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Every file this project accepts is written under public/ - the storage disk
 * is deliberately unused, so uploads are served directly by the web server with
 * no symlink and no streaming route.
 *
 * That makes public/ a directory an attacker would love to write to, so this
 * class is the only sanctioned way in and it never trusts the request:
 *
 *  - the stored name is generated here; the client-supplied name is discarded,
 *    which removes ".php", "../" and null-byte tricks in one move
 *  - the extension comes from the file's own sniffed mime, not from the name
 *  - the content is re-checked with getimagesize(), so a PHP script renamed
 *    to .jpg (and passing a spoofed Content-Type) is rejected
 *  - deletes are confined to the configured upload root, so a tampered
 *    database path cannot be used to unlink files elsewhere
 */
final class PublicUpload
{
    /**
     * Sniffed mime => extension we are willing to write. Anything not listed
     * here never reaches the disk.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Store an upload and return its path relative to public/, which is what
     * goes in the database (e.g. "uploads/products/9f2a1c4b8e.webp").
     *
     * @param  string  $bucket  key under config('admin.uploads')
     */
    public static function store(UploadedFile $file, string $bucket): string
    {
        $directory = self::directoryFor($bucket);

        // getMimeType() sniffs the file's own bytes. getClientMimeType() is
        // just a request header and is trivially forged, so it is not used.
        $mime = (string) $file->getMimeType();

        if (! isset(self::ALLOWED[$mime])) {
            throw new ApiException(
                'That file type is not allowed. Upload a JPG, PNG or WebP image.',
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // A polyglot can pass a mime sniff; a real raster image cannot fail
        // this. Also rejects a zero-byte or truncated upload.
        if (@getimagesize($file->getRealPath()) === false) {
            throw new ApiException(
                'That file is not a readable image.',
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $absolute = public_path($directory);

        if (! is_dir($absolute) && ! @mkdir($absolute, 0755, true) && ! is_dir($absolute)) {
            throw new ApiException(
                'The upload directory could not be created.',
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        // Name is ours: random, lowercase, with an extension chosen from the
        // sniffed mime rather than from anything the client sent.
        $name = Str::lower(Str::random(24)).'-'.now()->format('YmdHis').'.'.self::ALLOWED[$mime];

        $file->move($absolute, $name);

        return $directory.'/'.$name;
    }

    /**
     * Replace a file, removing the previous one only after the new one is
     * safely on disk - so a failed upload never leaves the record imageless.
     */
    public static function replace(UploadedFile $file, string $bucket, ?string $previous): string
    {
        $path = self::store($file, $bucket);

        self::delete($previous, $bucket);

        return $path;
    }

    /**
     * Remove a stored file. Silently ignores anything that is missing or that
     * resolves outside the bucket's own directory.
     */
    public static function delete(?string $path, string $bucket): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $root = realpath(public_path(self::directoryFor($bucket)));

        if ($root === false) {
            return;
        }

        $target = realpath(public_path($path));

        // realpath() has already resolved any "..", so this comparison is what
        // stops a doctored path from deleting, say, public/index.php.
        if ($target === false || ! str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($target)) {
            @unlink($target);
        }
    }

    /**
     * Validation rules for an image field in this bucket, so every request
     * class enforces the same limits without repeating them.
     *
     * @return array<int, string>
     */
    public static function rules(string $bucket, bool $required = false): array
    {
        $config = self::config($bucket);

        return [
            $required ? 'required' : 'nullable',
            'image',
            'mimes:'.implode(',', $config['mimes']),
            'max:'.$config['max_kb'],
        ];
    }

    private static function directoryFor(string $bucket): string
    {
        return trim(self::config($bucket)['path'], '/');
    }

    /**
     * @return array{path: string, max_kb: int, mimes: array<int, string>}
     */
    private static function config(string $bucket): array
    {
        $config = config('admin.uploads.'.$bucket);

        if (! is_array($config)) {
            // A programming error, not a client one - fail loudly in the log
            // rather than writing to an unintended directory.
            throw new \InvalidArgumentException("Unknown upload bucket [{$bucket}].");
        }

        return $config;
    }
}
