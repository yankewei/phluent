<?php

declare(strict_types=1);

namespace App\Sink\Contract;

interface SinkWriter
{
    public function write(string $data): void;

    public function close(): void;
}
