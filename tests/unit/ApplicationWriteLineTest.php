<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApplicationWriteLineTest extends TestCase
{
    public function testWriteLineSkipsOversizedLine(): void
    {
        $app = new Application();
        $writer = new class {
            public string $data = '';

            public function write(string $data): void
            {
                $this->data .= $data;
            }
        };

        $outputs = [[
            'writer' => [
                'type' => 'file',
                'handle' => $writer,
            ],
            'format' => 'ndjson',
        ]];

        $method = new ReflectionMethod($app, 'writeLine');
        $method->setAccessible(true);

        $method->invoke($app, $outputs, "123456\n", 5);
        $this->assertSame('', $writer->data);

        $method->invoke($app, $outputs, "1234\n", 5);
        $this->assertSame("1234\n", $writer->data);
    }
}
