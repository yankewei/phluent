<?php

declare(strict_types=1);

namespace App\Sink\Contract;

/**
 * @phpstan-import-type SinkConfig from \App\Config
 */
interface SinkDriver
{
    public function type(): string;

    /**
     * @param SinkConfig $sink
     */
    public function uniqueKey(array $sink): string;

    /**
     * @param SinkConfig $sink
     */
    public function prepare(array $sink): void;

    /**
     * @param SinkConfig $sink
     */
    public function formatLine(string $line, array $sink): ?string;

    /**
     * @param SinkConfig $sink
     */
    public function openWriter(array $sink): SinkWriter;
}
