<?php

declare(strict_types=1);

use Amp\File\Driver\BlockingFilesystemDriver;
use PHPUnit\Framework\TestCase;

use function Amp\File\filesystem;

final class ApplicationBatchProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        filesystem(new BlockingFilesystemDriver());
    }

    public function testBufferedOutputMatchesExpectedFixture(): void
    {
        $baseDir = TestFilesystem::createTempDir();

        try {
            $inputPath = $baseDir . '/input/core.log';
            $outputPath = $baseDir . '/output/result.ndjson';

            TestFilesystem::copyFixture('input/core.log', $inputPath);
            TestFilesystem::writeFile($outputPath, '');

            $sinks = [[
                'path' => $outputPath,
                'format' => 'ndjson',
                'compression' => null,
                'batch_max_bytes' => 1,
                'batch_max_wait_seconds' => 60,
            ]];

            $app = new Application();
            ApplicationRunner::enqueueRead($app, $inputPath, null, $sinks);

            $expected = TestFilesystem::readFile(TestFilesystem::fixturePath('expected/core.ndjson'));
            $actual = TestFilesystem::readFile($outputPath);

            $this->assertSame($expected, $actual);
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
