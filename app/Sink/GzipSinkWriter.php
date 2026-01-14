<?php

declare(strict_types=1);

namespace App\Sink;

use RuntimeException;

final class GzipSinkWriter implements SinkWriter
{
    /**
     * @var resource
     */
    private $handle;

    public function __construct(string $path)
    {
        if (!function_exists('gzopen')) {
            throw new RuntimeException('gzip compression requires the zlib extension.');
        }

        $handle = gzopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException("Failed to open gzip sink: {$path}");
        }

        $this->handle = $handle;
    }

    public function write(string $data): void
    {
        gzwrite($this->handle, $data);
    }

    public function close(): void
    {
        gzclose($this->handle);
    }
}
