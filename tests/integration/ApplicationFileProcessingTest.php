<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApplicationFileProcessingTest extends TestCase
{
    public function testWritesExpectedOutputFromInput(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $outputHandle = null;

        try {
            $inputPath = $baseDir . '/input/core.log';
            $outputPath = $baseDir . '/output/result.ndjson';

            TestFilesystem::copyFixture('input/core.log', $inputPath);
            TestFilesystem::writeFile($outputPath, '');

            $outputHandle = fopen($outputPath, 'ab');
            $this->assertNotFalse($outputHandle);

            $writer = new class ($outputHandle) {
                public function __construct(private $handle) {}

                public function write(string $data): void
                {
                    fwrite($this->handle, $data);
                }
            };

            $outputs = [[
                'writer' => [
                    'type' => 'file',
                    'handle' => $writer,
                ],
                'format' => 'ndjson',
            ]];

            $contents = TestFilesystem::readFile($inputPath);
            $lines = preg_split('/(?<=\\n)/', $contents, -1, PREG_SPLIT_NO_EMPTY);
            $app = new Application();
            $method = new ReflectionMethod($app, 'writeLine');
            $method->setAccessible(true);

            foreach ($lines as $line) {
                $method->invoke($app, $outputs, $line, null);
            }

            $expected = TestFilesystem::readFile(TestFilesystem::fixturePath('expected/core.ndjson'));
            $actual = TestFilesystem::readFile($outputPath);

            $this->assertSame($expected, $actual);
        } finally {
            if (is_resource($outputHandle)) {
                fclose($outputHandle);
            }
            TestFilesystem::removeDir($baseDir);
        }
    }
}
