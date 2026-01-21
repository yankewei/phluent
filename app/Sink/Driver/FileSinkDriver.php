<?php

declare(strict_types=1);

namespace App\Sink\Driver;

use Amp\File;
use App\Sink\Contract\SinkDriver;
use App\Sink\Contract\SinkWriter;
use App\Sink\Writer\FileSinkWriter;
use App\Sink\Writer\GzipSinkWriter;
use RuntimeException;

/**
 * @phpstan-import-type FileSinkConfig from \App\Config
 */
final class FileSinkDriver implements SinkDriver
{
    public function type(): string
    {
        return 'file';
    }

    /**
     * @param FileSinkConfig $sink
     */
    public function uniqueKey(array $sink): string
    {
        $path = $sink['path'];
        $format = $sink['format'];
        $compression = $sink['compression'];
        $batchMaxBytes = $sink['batch_max_bytes'];
        $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'];

        return hash('sha256', serialize([
            'path' => $path,
            'format' => $format,
            'compression' => $compression,
            'batch_max_bytes' => $batchMaxBytes,
            'batch_max_wait_seconds' => $batchMaxWaitSeconds,
        ]));
    }

    /**
     * @param FileSinkConfig $sink
     */
    public function prepare(array $sink): void
    {
        $path = $sink['path'];
        if ($path === '') {
            throw new RuntimeException('Sink path is required for file driver.');
        }

        $dir = dirname($path);
        if ($dir === '' || $dir === '.') {
            return;
        }

        File\createDirectoryRecursively($dir);
    }

    /**
     * @param FileSinkConfig $sink
     */
    public function formatLine(string $line, array $sink): string
    {
        $format = $sink['format'];
        if ($format === 'ndjson') {
            return $line;
        }

        throw new RuntimeException("Unsupported sink format: {$format}");
    }

    /**
     * @param FileSinkConfig $sink
     */
    public function openWriter(array $sink): SinkWriter
    {
        $path = $sink['path'];
        if ($path === '') {
            throw new RuntimeException('Sink path is required for file driver.');
        }

        $compression = $sink['compression'];
        if ($compression === 'gzip') {
            return new GzipSinkWriter($path);
        }

        return new FileSinkWriter($path);
    }
}
