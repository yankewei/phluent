<?php

declare(strict_types=1);

namespace App\Sink;

use Amp\File;
use RuntimeException;

final class FileSinkDriver implements SinkDriver
{
    public function type(): string
    {
        return 'file';
    }

    public function uniqueKey(array $sink): string
    {
        $path = (string) ($sink['path'] ?? '');
        $format = (string) ($sink['format'] ?? '');
        $compression = $sink['compression'] ?? '';
        $batchMaxBytes = $sink['batch_max_bytes'] ?? '';
        $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'] ?? '';

        return $path
            . '|'
            . $format
            . '|'
            . $compression
            . '|'
            . $batchMaxBytes
            . '|'
            . $batchMaxWaitSeconds;
    }

    public function prepare(array $sink): void
    {
        $path = $sink['path'] ?? '';
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Sink path is required for file driver.');
        }

        $dir = dirname($path);
        if ($dir === '' || $dir === '.') {
            return;
        }

        File\createDirectoryRecursively($dir);
    }

    public function formatLine(string $line, array $sink): ?string
    {
        $format = $sink['format'] ?? 'ndjson';
        if ($format === 'ndjson') {
            return $line;
        }

        throw new RuntimeException("Unsupported sink format: {$format}");
    }

    public function openWriter(array $sink): SinkWriter
    {
        $path = $sink['path'] ?? '';
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Sink path is required for file driver.');
        }

        $compression = $sink['compression'] ?? null;
        if ($compression === 'gzip') {
            return new GzipSinkWriter($path);
        }

        return new FileSinkWriter($path);
    }
}
