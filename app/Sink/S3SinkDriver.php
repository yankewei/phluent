<?php

declare(strict_types=1);

namespace App\Sink;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use RuntimeException;

final class S3SinkDriver implements SinkDriver
{
    /**
     * @var array<string, S3Client>
     */
    private array $clients = [];

    /**
     * @var (callable(array): S3Client)|null
     */
    private $clientFactory;

    public function __construct(?callable $clientFactory = null)
    {
        $this->clientFactory = $clientFactory;
    }

    public function type(): string
    {
        return 's3';
    }

    public function uniqueKey(array $sink): string
    {
        $bucket = (string) ($sink['bucket'] ?? '');
        $prefix = (string) ($sink['prefix'] ?? '');
        $format = (string) ($sink['format'] ?? '');
        $compression = $sink['compression'] ?? '';
        $batchMaxBytes = $sink['batch_max_bytes'] ?? '';
        $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'] ?? '';
        $region = (string) ($sink['region'] ?? '');
        $endpoint = (string) ($sink['endpoint'] ?? '');
        $pathStyle = !empty($sink['use_path_style_endpoint']);
        $accessKey = '';
        $credentials = $sink['credentials'] ?? null;
        if (is_array($credentials)) {
            $accessKey = (string) ($credentials['access_key_id'] ?? '');
        }

        return hash('sha256', serialize([
            'bucket' => $bucket,
            'prefix' => $prefix,
            'format' => $format,
            'compression' => $compression,
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $pathStyle,
            'access_key_id' => $accessKey,
            'batch_max_bytes' => $batchMaxBytes,
            'batch_max_wait_seconds' => $batchMaxWaitSeconds,
        ]));
    }

    public function prepare(array $sink): void
    {
        $bucket = $sink['bucket'] ?? '';
        if (!is_string($bucket) || $bucket === '') {
            throw new RuntimeException('S3 bucket is required for s3 sink.');
        }
    }

    public function formatLine(string $line, array $sink): ?string
    {
        $format = $sink['format'] ?? 'ndjson';
        if ($format === 'ndjson') {
            return $line;
        }

        throw new RuntimeException("Unsupported sink format: {$format}");
    }

    public function openWriter(array $sink): SinkWriter
    {
        $bucket = $sink['bucket'] ?? '';
        if (!is_string($bucket) || $bucket === '') {
            throw new RuntimeException('S3 bucket is required for s3 sink.');
        }

        $prefix = $sink['prefix'] ?? '';
        $format = $sink['format'] ?? 'ndjson';
        $compression = $sink['compression'] ?? null;
        $key = $this->buildObjectKey((string) $prefix, (string) $format, is_string($compression) ? $compression : null);
        $contentType = $this->contentTypeForFormat((string) $format);
        $contentEncoding = $compression === 'gzip' ? 'gzip' : null;

        return new S3SinkWriter(
            $this->getClient($sink),
            $bucket,
            $key,
            $contentType,
            $contentEncoding,
        );
    }

    private function getClient(array $sink): S3Client
    {
        $key = $this->clientKey($sink);
        $client = $this->clients[$key] ?? null;
        if ($client === null) {
            $client = $this->createClient($sink);
            $this->clients[$key] = $client;
        }

        return $client;
    }

    private function clientKey(array $sink): string
    {
        $region = (string) ($sink['region'] ?? '');
        $endpoint = (string) ($sink['endpoint'] ?? '');
        $pathStyle = !empty($sink['use_path_style_endpoint']) ? '1' : '0';
        $credentials = $sink['credentials'] ?? null;
        $accessKey = '';
        if (is_array($credentials)) {
            $accessKey = (string) ($credentials['access_key_id'] ?? '');
        }

        return $region . '|' . $endpoint . '|' . $pathStyle . '|' . $accessKey;
    }

    protected function createClient(array $sink): S3Client
    {
        if ($this->clientFactory !== null) {
            return ($this->clientFactory)($sink);
        }

        $region = $sink['region'] ?? null;
        if (!is_string($region) || $region === '') {
            $region = getenv('AWS_REGION');
            if ($region === false || $region === '') {
                $region = getenv('AWS_DEFAULT_REGION');
            }
            if ($region === false || $region === '') {
                $region = 'us-east-1';
            }
        }

        $config = [
            'version' => 'latest',
            'region' => $region,
        ];

        $endpoint = $sink['endpoint'] ?? null;
        if (!is_string($endpoint) || $endpoint === '') {
            $endpoint = getenv('AWS_ENDPOINT_URL');
        }
        if (is_string($endpoint) && $endpoint !== '') {
            $config['endpoint'] = $endpoint;
        }

        $usePathStyle = $sink['use_path_style_endpoint'] ?? null;
        if (is_bool($usePathStyle)) {
            $config['use_path_style_endpoint'] = $usePathStyle;
        }

        $credentials = $sink['credentials'] ?? null;
        if (is_array($credentials)) {
            $accessKey = (string) ($credentials['access_key_id'] ?? '');
            $secretKey = (string) ($credentials['secret_access_key'] ?? '');
            $sessionToken = $credentials['session_token'] ?? null;
            if ($accessKey !== '' && $secretKey !== '') {
                $config['credentials'] = new Credentials(
                    $accessKey,
                    $secretKey,
                    is_string($sessionToken) ? $sessionToken : null,
                );
            }
        }

        return new S3Client($config);
    }

    private function buildObjectKey(string $prefix, string $format, ?string $compression): string
    {
        $date = date('Ymd-His');
        $normalizedPrefix = trim($prefix);
        $extension = $this->formatExtension($format, $compression);

        $suffix = bin2hex(random_bytes(3));
        $filename = $date . '-' . $suffix;

        if ($normalizedPrefix !== '') {
            if (str_ends_with($normalizedPrefix, '/')) {
                $filename = rtrim($normalizedPrefix, '/') . '/' . $filename;
            } else {
                $filename = $normalizedPrefix . '-' . $filename;
            }
        }

        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        return $filename;
    }

    private function formatExtension(string $format, ?string $compression): string
    {
        $base = match ($format) {
            'ndjson' => 'ndjson',
            default => '',
        };

        if ($compression === 'gzip') {
            return $base === '' ? 'gz' : $base . '.gz';
        }

        return $base;
    }

    private function contentTypeForFormat(string $format): ?string
    {
        return match ($format) {
            'ndjson' => 'application/x-ndjson',
            default => null,
        };
    }
}
