<?php

declare(strict_types=1);

use App\Application;
use App\Sink\FileSinkDriver;
use App\Sink\SinkWriter;
use PHPUnit\Framework\TestCase;

final class ApplicationWriteLineTest extends TestCase
{
    public function testWriteLineSkipsOversizedLine(): void
    {
        $baseDir = TestFilesystem::createTempDir();

        try {
            $config = ConfigFactory::loadValidConfig($baseDir);
            $app = new Application($config);
            $writer = new class implements SinkWriter {
                public string $data = '';

                public function write(string $data): void
                {
                    $this->data .= $data;
                }

                public function close(): void
                {
                }
            };

            $outputs = [[
                'driver' => new FileSinkDriver(),
                'sink' => [
                    'type' => 'file',
                    'path' => $baseDir . '/output/result.ndjson',
                    'format' => 'ndjson',
                    'compression' => null,
                ],
                'writer' => $writer,
                'batch_max_bytes' => null,
                'batch_max_wait_seconds' => null,
            ]];

            $method = new ReflectionMethod($app, 'writeLine');
            $method->setAccessible(true);

            $method->invoke($app, $outputs, "123456\n", 5);
            $this->assertSame('', $writer->data);

            $method->invoke($app, $outputs, "1234\n", 5);
            $this->assertSame("1234\n", $writer->data);
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
