<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Log\Log;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores an uploaded image somewhere the site can serve it.
 *
 * File upload is the easiest place in an application to open a hole, so
 * nothing the browser sends is believed:
 *
 * - The reported MIME type and the original extension are ignored. The file
 *   is opened and inspected, and only a real JPEG, PNG or WebP is kept.
 * - The stored name is generated here, never taken from the upload, so a
 *   crafted name cannot escape the directory or land as `.php` in a folder
 *   the web server executes.
 * - Size is capped before anything touches disk.
 */
class ImageUploadService
{
    /** Uploads land here, under webroot so they can be served. */
    public const DIRECTORY = 'uploads';

    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Image types accepted, mapped to the extension we save them under.
     *
     * @var array<int, string>
     */
    protected const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Validates and stores an upload.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $file
     * @param string $prefix Leading part of the stored name, for readability.
     * @return array{ok: bool, filename?: string, error?: string}
     */
    public function store(UploadedFileInterface $file, string $prefix = 'image'): array
    {
        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was chosen.'];
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->describeUploadError($file->getError())];
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_BYTES) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'That image is %s. The limit is %s.',
                    $this->formatBytes((int)$file->getSize()),
                    $this->formatBytes(self::MAX_BYTES)
                ),
            ];
        }

        /*
         * Inspect the bytes rather than the name. getimagesize reads the
         * file's own header, so a .php renamed to .jpg fails here.
         */
        $temp = $file->getStream()->getMetadata('uri');

        if (!is_string($temp) || !is_readable($temp)) {
            return ['ok' => false, 'error' => 'The upload could not be read. Please try again.'];
        }

        $info = @getimagesize($temp);

        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            return [
                'ok' => false,
                'error' => 'That file is not a JPEG, PNG or WebP image.',
            ];
        }

        $extension = self::ALLOWED[$info[2]];

        // Random component keeps a replacement from being served from cache
        // under the old name, and makes stored names unguessable.
        $filename = sprintf(
            '%s-%s.%s',
            preg_replace('/[^a-z0-9-]/', '', strtolower($prefix)) ?: 'image',
            bin2hex(random_bytes(8)),
            $extension
        );

        $directory = $this->directoryPath();

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            Log::error('Could not create the upload directory: ' . $directory);

            return ['ok' => false, 'error' => 'The upload folder could not be created.'];
        }

        try {
            $file->moveTo($directory . $filename);
        } catch (\Throwable $e) {
            Log::error('Image upload failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'The image could not be saved. Please try again.'];
        }

        return ['ok' => true, 'filename' => $filename];
    }

    /**
     * Deletes a previously stored image.
     *
     * Only ever touches a bare filename inside the upload directory, so a
     * stored value that somehow contained a path cannot reach other files.
     *
     * @param string|null $filename
     * @return void
     */
    public function delete(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $safe = basename($filename);
        $path = $this->directoryPath() . $safe;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return string Absolute path, with a trailing separator.
     */
    public function directoryPath(): string
    {
        return WWW_ROOT . 'img' . DS . self::DIRECTORY . DS;
    }

    /**
     * @param string $filename
     * @return bool Whether the named file is actually present.
     */
    public function exists(string $filename): bool
    {
        return $filename !== '' && is_file($this->directoryPath() . basename($filename));
    }

    protected function describeUploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE
                => 'That image is larger than the server allows.',
            UPLOAD_ERR_PARTIAL
                => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE
                => 'The server could not store the upload.',
            default
                => 'The upload failed. Please try again.',
        };
    }

    protected function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / 1024 / 1024, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
