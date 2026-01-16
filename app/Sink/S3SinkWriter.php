<?php

declare(strict_types=1);

namespace App\Sink;

use Amp\File;
use Aws\S3\S3Client;
use RuntimeException;

final class S3SinkWriter implements SinkWriter
{
    private S3Client $client;
    private string $bucket;
    private string $key;
    private string $path;
    private ?string $contentType;
    private ?string $contentEncoding;
    private bool $gzip;
    private bool $closed = false;

    /**
     * @var Amp\File\File|resource
     */
    private $handle;

    public function __construct(
        S3Client $client,
        string $bucket,
        string $key,
        ?string $contentType,
        ?string $contentEncoding,
    ) {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->key = $key;
        $this->contentType = $contentType;
        $this->contentEncoding = $contentEncoding;
        $this->path = $this->createTempPath();
        $this->gzip = $contentEncoding === 'gzip';

        if ($this->gzip) {
            if (!function_exists('gzopen')) {
                throw new RuntimeException('gzip compression requires the zlib extension.');
            }
            $handle = gzopen($this->path, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Failed to open gzip temp file: {$this->path}");
            }
            $this->handle = $handle;
            return;
        }

        $this->handle = File\openFile($this->path, 'w');
    }

    public function write(string $data): void
    {
        if ($this->gzip) {
            $written = gzwrite($this->handle, $data);
            if ($written === false) {
                throw new RuntimeException('Failed to write gzip buffer.');
            }
            return;
        }

        $this->handle->write($data);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        try {
            if ($this->gzip) {
                gzclose($this->handle);
            } else {
                $this->handle->close();
            }

            $params = [
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'SourceFile' => $this->path,
            ];

            if ($this->contentType !== null) {
                $params['ContentType'] = $this->contentType;
            }

            if ($this->contentEncoding !== null) {
                $params['ContentEncoding'] = $this->contentEncoding;
            }

            $this->client->putObject($params);
        } finally {
            $this->safeUnlink($this->path);
        }
    }

    private function createTempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phluent-s3-');
        if ($path === false) {
            throw new RuntimeException('Failed to create temp file for S3 writer.');
        }

        return $path;
    }

    private function safeUnlink(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        $previous = set_error_handler(static function (int $type, string $message): void {
            throw new RuntimeException($message);
        });

        try {
            unlink($path);
        } catch (RuntimeException) {
            return;
        } finally {
            restore_error_handler();
            if ($previous !== null) {
                set_error_handler($previous);
            }
        }
    }
}
