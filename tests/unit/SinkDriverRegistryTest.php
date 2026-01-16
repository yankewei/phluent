<?php

declare(strict_types=1);

use App\Sink\SinkDriver;
use App\Sink\SinkDriverRegistry;
use App\Sink\SinkWriter;
use PHPUnit\Framework\TestCase;

final class SinkDriverRegistryTest extends TestCase
{
    public function testReturnsRegisteredDriver(): void
    {
        $driver = new class implements SinkDriver {
            public function type(): string
            {
                return 'demo';
            }

            public function uniqueKey(array $sink): string
            {
                return 'demo';
            }

            public function prepare(array $sink): void
            {
            }

            public function formatLine(string $line, array $sink): ?string
            {
                return $line;
            }

            public function openWriter(array $sink): SinkWriter
            {
                return new class implements SinkWriter {
                    public function write(string $data): void
                    {
                    }

                    public function close(): void
                    {
                    }
                };
            }
        };

        $registry = new SinkDriverRegistry([$driver]);

        $this->assertSame($driver, $registry->get('demo'));
    }

    public function testMissingDriverThrows(): void
    {
        $registry = new SinkDriverRegistry();

        ExceptionAssertions::assertRuntimeExceptionMessageContains(
            $this,
            static fn() => $registry->get('missing'),
            'Unsupported sink type',
        );
    }
}
