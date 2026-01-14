<?php

declare(strict_types=1);

use Amp\File\Driver\BlockingFilesystemDriver;
use App\Sink\FileSinkDriver;
use PHPUnit\Framework\TestCase;

use function Amp\File\filesystem;

final class FileSinkDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        filesystem(new BlockingFilesystemDriver());
    }

    public function testUniqueKeyChangesWithConfig(): void
    {
        $driver = new FileSinkDriver();
        $baseSink = [
            'path' => '/tmp/output.ndjson',
            'format' => 'ndjson',
            'compression' => null,
            'batch_max_bytes' => null,
            'batch_max_wait_seconds' => null,
        ];

        $this->assertSame($driver->uniqueKey($baseSink), $driver->uniqueKey($baseSink));

        $modified = $baseSink;
        $modified['compression'] = 'gzip';

        $this->assertNotSame($driver->uniqueKey($baseSink), $driver->uniqueKey($modified));
    }

    public function testPrepareCreatesDirectory(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $outputPath = $baseDir . '/nested/output.ndjson';
        $driver = new FileSinkDriver();

        try {
            $this->assertFalse(is_dir(dirname($outputPath)));
            $driver->prepare(['path' => $outputPath]);
            $this->assertTrue(is_dir(dirname($outputPath)));
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }

    public function testFormatLineSupportsNdjson(): void
    {
        $driver = new FileSinkDriver();
        $result = $driver->formatLine("line\n", ['format' => 'ndjson']);

        $this->assertSame("line\n", $result);
    }

    public function testFormatLineRejectsUnknownFormat(): void
    {
        $driver = new FileSinkDriver();

        ExceptionAssertions::assertRuntimeExceptionMessageContains(
            $this,
            fn () => $driver->formatLine("line\n", ['format' => 'csv']),
            'Unsupported sink format',
        );
    }

    public function testOpenWriterWritesToFile(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $outputPath = $baseDir . '/output/result.ndjson';
        $driver = new FileSinkDriver();

        try {
            $driver->prepare(['path' => $outputPath]);
            $writer = $driver->openWriter([
                'path' => $outputPath,
                'compression' => null,
            ]);
            $writer->write("hello\n");
            $writer->close();

            $this->assertSame("hello\n", TestFilesystem::readFile($outputPath));
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
