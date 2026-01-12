<?php

declare(strict_types=1);

use Amp\File\Driver\BlockingFilesystemDriver;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

use function Amp\File\filesystem;

final class ApplicationBatchBufferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        filesystem(new BlockingFilesystemDriver());
    }

    public function testFlushesOnSizeThresholdUsingTempFile(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $outputPath = $baseDir . '/output/result.ndjson';
        TestFilesystem::writeFile($outputPath, '');

        $app = new Application();
        $method = new ReflectionMethod($app, 'bufferLine');
        $method->setAccessible(true);

        $output = [
            'writer' => null,
            'format' => 'ndjson',
            'path' => $outputPath,
            'compression' => null,
            'batch_max_bytes' => 5,
            'batch_max_wait_seconds' => 60,
        ];

        $method->invoke($app, $output, "hi\n");

        $buffers = $this->getBuffers($app);
        $bufferPath = $buffers[$outputPath]['path'];
        $this->assertNotNull($bufferPath);
        $this->assertFileExists($bufferPath);

        $method->invoke($app, $output, "there\n");

        $this->assertSame("hi\nthere\n", TestFilesystem::readFile($outputPath));
        $this->assertFileDoesNotExist($bufferPath);

        $buffers = $this->getBuffers($app);
        $this->assertSame(0, $buffers[$outputPath]['size']);
        $this->assertNull($buffers[$outputPath]['handle']);
        $this->assertNull($buffers[$outputPath]['path']);

        TestFilesystem::removeDir($baseDir);
    }

    public function testFlushesOnIdleTimeout(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $outputPath = $baseDir . '/output/result.ndjson';
        TestFilesystem::writeFile($outputPath, '');

        $app = new Application();
        $method = new ReflectionMethod($app, 'bufferLine');
        $method->setAccessible(true);

        $output = [
            'writer' => null,
            'format' => 'ndjson',
            'path' => $outputPath,
            'compression' => null,
            'batch_max_bytes' => 1024,
            'batch_max_wait_seconds' => 1,
        ];

        $method->invoke($app, $output, "idle\n");

        EventLoop::delay(2, static function (): void {
            EventLoop::getDriver()->stop();
        });
        EventLoop::run();

        $this->assertSame("idle\n", TestFilesystem::readFile($outputPath));

        TestFilesystem::removeDir($baseDir);
    }

    /**
     * @return array<string, array{handle:?Amp\File\File, path:?string, size:int, last_append_at:float, timer_id:?string, sink_path:string, compression:?string, max_bytes:int, max_wait_seconds:int}>
     */
    private function getBuffers(Application $app): array
    {
        $prop = new ReflectionProperty($app, 'buffers');
        $prop->setAccessible(true);
        return $prop->getValue($app);
    }
}
