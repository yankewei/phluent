<?php

declare(strict_types=1);

use Amp\File\Driver\BlockingFilesystemDriver;
use App\Application;
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
                'type' => 'file',
                'path' => $outputPath,
                'format' => 'ndjson',
                'compression' => null,
                'batch_max_bytes' => 1,
                'batch_max_wait_seconds' => 60,
            ]];

            $config = ConfigFactory::loadValidConfig($baseDir);
            $app = new Application($config);
            ApplicationRunner::enqueueRead($app, $inputPath, null, $sinks);

            $expected = TestFilesystem::readFile(TestFilesystem::fixturePath('expected/core.ndjson'));
            $actual = TestFilesystem::readFile($outputPath);

            $this->assertSame($expected, $actual);
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
