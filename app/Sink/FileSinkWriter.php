<?php

declare(strict_types=1);

namespace App\Sink;

use Amp\File;

final class FileSinkWriter implements SinkWriter
{
    private File\File $handle;

    public function __construct(string $path)
    {
        $this->handle = File\openFile($path, 'a');
    }

    public function write(string $data): void
    {
        $this->handle->write($data);
    }

    public function close(): void
    {
        $this->handle->close();
    }
}
