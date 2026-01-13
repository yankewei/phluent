<?php

declare(strict_types=1);

namespace App\Sink;

interface SinkDriver
{
    public function type(): string;

    public function uniqueKey(array $sink): string;

    public function prepare(array $sink): void;

    public function formatLine(string $line, array $sink): ?string;

    public function openWriter(array $sink): SinkWriter;
}
