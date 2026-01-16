<?php

declare(strict_types=1);

use App\Sink\S3SinkDriver;
use App\Sink\S3SinkWriter;
use Aws\Credentials\Credentials;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

final class S3SinkDriverTest extends TestCase
{
    public function testOpenWriterUploadsObject(): void
    {
        $mock = new MockHandler([new Result([])]);
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => new Credentials('key', 'secret'),
            'handler' => $mock,
        ]);

        $driver = new S3SinkDriver(static fn () => $client);

        $writer = $driver->openWriter([
            'bucket' => 'demo-bucket',
            'prefix' => 'logs',
            'format' => 'ndjson',
            'compression' => null,
            'batch_max_bytes' => null,
            'batch_max_wait_seconds' => null,
        ]);
        $this->assertInstanceOf(S3SinkWriter::class, $writer);
        $tempPath = (new ReflectionClass($writer))->getProperty('path');
        $tempPath->setAccessible(true);
        $pathValue = $tempPath->getValue($writer);
        $writer->write("hello\n");
        $writer->close();

        $command = $mock->getLastCommand();
        $this->assertSame('PutObject', $command->getName());

        $params = $command->toArray();
        $this->assertSame('demo-bucket', $params['Bucket']);
        $this->assertSame('application/x-ndjson', $params['ContentType']);
        $this->assertArrayHasKey('Key', $params);
        $this->assertMatchesRegularExpression(
            '/^logs-\\d{8}-\\d{6}-[a-f0-9]{6}\\.ndjson$/',
            $params['Key'],
        );
        $this->assertFalse(file_exists($pathValue));
    }

    public function testOpenWriterUploadsGzipObject(): void
    {
        if (!function_exists('gzopen')) {
            $this->markTestSkipped('gzip tests require the zlib extension.');
        }

        $mock = new MockHandler([new Result([])]);
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => new Credentials('key', 'secret'),
            'handler' => $mock,
        ]);

        $driver = new S3SinkDriver(static fn () => $client);

        $writer = $driver->openWriter([
            'bucket' => 'demo-bucket',
            'prefix' => 'logs',
            'format' => 'ndjson',
            'compression' => 'gzip',
            'batch_max_bytes' => null,
            'batch_max_wait_seconds' => null,
        ]);
        $this->assertInstanceOf(S3SinkWriter::class, $writer);
        $tempPath = (new ReflectionClass($writer))->getProperty('path');
        $tempPath->setAccessible(true);
        $pathValue = $tempPath->getValue($writer);
        $writer->write("hello\n");
        $writer->close();

        $command = $mock->getLastCommand();
        $this->assertSame('PutObject', $command->getName());

        $params = $command->toArray();
        $this->assertSame('demo-bucket', $params['Bucket']);
        $this->assertSame('application/x-ndjson', $params['ContentType']);
        $this->assertSame('gzip', $params['ContentEncoding']);
        $this->assertArrayHasKey('Key', $params);
        $this->assertMatchesRegularExpression(
            '/^logs-\\d{8}-\\d{6}-[a-f0-9]{6}\\.ndjson\\.gz$/',
            $params['Key'],
        );
        $this->assertFalse(file_exists($pathValue));
    }
}
