<?php

declare(strict_types=1);

namespace BillTo\Http;

/**
 * Binary file returned by the API (invoice PDF, KSeF XML).
 */
final class BinaryFile
{
    public function __construct(
        public readonly string $content,
        public readonly string $contentType,
        public readonly ?string $filename,
    ) {}

    public static function fromResponse(ApiResponse $response, string $fallbackFilename): self
    {
        return new self(
            $response->body,
            $response->header('content-type') ?? 'application/octet-stream',
            self::filenameFromDisposition($response->header('content-disposition')) ?? $fallbackFilename,
        );
    }

    public function size(): int
    {
        return strlen($this->content);
    }

    /** Write the file to disk; returns the number of bytes written. */
    public function saveTo(string $path): int
    {
        $written = file_put_contents($path, $this->content);

        if ($written === false) {
            throw new \RuntimeException("Could not write file: {$path}");
        }

        return $written;
    }

    /** Write the file into a directory using the Content-Disposition filename; returns the full path. */
    public function saveIn(string $directory): string
    {
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.($this->filename ?? 'download.bin');
        $this->saveTo($path);

        return $path;
    }

    private static function filenameFromDisposition(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $header, $m) === 1) {
            return rawurldecode(trim($m[1], '"'));
        }

        if (preg_match('/filename="?([^";]+)"?/i', $header, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }
}
