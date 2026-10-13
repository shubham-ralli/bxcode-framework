<?php

declare(strict_types=1);

namespace BXCode\Http;

use BXCode\Storage\Filesystem;

/**
 * A validated uploaded file. The client-supplied filename is never used to build
 * a storage path — store() generates a random name with the validated
 * extension, so a malicious filename cannot traverse or overwrite anything.
 */
final class UploadedFile
{
    /**
     * @param int  $error    One of the UPLOAD_ERR_* constants.
     * @param bool $testMode When true, store() copies instead of move_uploaded_file()
     *                       (move_uploaded_file only accepts genuine HTTP uploads).
     */
    public function __construct(
        private readonly string $clientName,
        private readonly string $tmpPath,
        private readonly int $size,
        private readonly int $error = UPLOAD_ERR_OK,
        private readonly bool $testMode = false,
    ) {
    }

    /** Build from a single $_FILES[...] entry. */
    public static function fromArray(array $file): self
    {
        return new self(
            clientName: (string) ($file['name'] ?? ''),
            tmpPath: (string) ($file['tmp_name'] ?? ''),
            size: (int) ($file['size'] ?? 0),
            error: (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
        );
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '' && is_file($this->tmpPath);
    }

    /** Lowercased client extension (no dot). */
    public function extension(): string
    {
        return strtolower(pathinfo($this->clientName, PATHINFO_EXTENSION));
    }

    public function size(): int
    {
        return $this->size;
    }

    public function clientName(): string
    {
        return $this->clientName;
    }

    /**
     * Validate the upload. Returns a list of error messages; empty means valid.
     *
     * @param list<string> $allowedExtensions lowercased, no dot (e.g. ['jpg','png'])
     * @return list<string>
     */
    public function validate(int $maxBytes, array $allowedExtensions): array
    {
        $errors = [];

        if (!$this->isValid()) {
            $errors[] = 'The file failed to upload.';
            return $errors; // nothing else is meaningful
        }

        if ($this->size > $maxBytes) {
            $errors[] = "The file may not be larger than {$maxBytes} bytes.";
        }

        if ($allowedExtensions !== [] && !in_array($this->extension(), $allowedExtensions, true)) {
            $errors[] = 'The file type is not allowed.';
        }

        return $errors;
    }

    /**
     * Move the upload into $disk under $directory, with a random, safe filename.
     * Returns the stored path relative to the disk root.
     */
    public function store(Filesystem $disk, string $directory = ''): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot store an invalid upload.');
        }

        $ext      = $this->extension();
        $name     = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
        $relative = ltrim(($directory === '' ? '' : rtrim($directory, '/') . '/') . $name, '/');

        $target = $disk->path($relative); // validates it stays within the root
        $dir    = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the storage directory.');
        }

        $moved = $this->testMode
            ? @copy($this->tmpPath, $target)
            : @move_uploaded_file($this->tmpPath, $target);

        if (!$moved) {
            throw new \RuntimeException('Could not move the uploaded file.');
        }

        return $relative;
    }
}
