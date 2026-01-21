<?php

declare(strict_types=1);

namespace App;

use Devium\Toml\Toml;
use Devium\Toml\TomlError;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validatable;
use Respect\Validation\Validator;
use RuntimeException;

/**
 * @phpstan-type SourceConfig array{type:'file', dir:string, max_bytes:?int, done_suffix?:string}
 * @phpstan-type SinkBatch array{max_bytes:int, max_wait_seconds:int}
 * @phpstan-type SinkCredentials array{access_key_id:string, secret_access_key:string, session_token?:string}
 * @phpstan-type RawSourceConfig array{type:string, dir:string, max_bytes?:int, done_suffix?:string}
 * @phpstan-type RawSinkConfig array{
 *   type:string,
 *   inputs:array<int, string>,
 *   prefix?:string,
 *   format?:string,
 *   compression?:string,
 *   batch?:SinkBatch,
 *   dir?:string,
 *   bucket?:string,
 *   region?:string,
 *   endpoint?:string,
 *   use_path_style_endpoint?:bool,
 *   credentials?:SinkCredentials
 * }
 * @phpstan-type FileSinkConfig array{
 *   type:'file',
 *   inputs:array<int, string>,
 *   format:string,
 *   compression:?string,
 *   batch:?SinkBatch,
 *   batch_max_bytes:?int,
 *   batch_max_wait_seconds:?int,
 *   buffer_enabled:bool,
 *   prefix?:string,
 *   dir:string,
 *   path:string
 * }
 * @phpstan-type S3SinkConfig array{
 *   type:'s3',
 *   inputs:array<int, string>,
 *   format:string,
 *   compression:?string,
 *   batch:?SinkBatch,
 *   batch_max_bytes:?int,
 *   batch_max_wait_seconds:?int,
 *   buffer_enabled:bool,
 *   prefix?:string,
 *   bucket:string,
 *   region:?string,
 *   endpoint:?string,
 *   use_path_style_endpoint:bool,
 *   credentials:?SinkCredentials
 * }
 * @phpstan-type SinkConfig FileSinkConfig|S3SinkConfig
 */
final class Config
{
    /**
     * Validated and normalized source config entries from Config::load().
     *
     * @var array<string, SourceConfig>
     */
    public readonly array $sources;

    /**
     * Validated and normalized sink config entries from Config::load().
     *
     * @var array<string, SinkConfig>
     */
    public readonly array $sinks;

    /**
     * Base directory used to resolve relative paths from the config file location.
     */
    public readonly string $baseDir;

    /**
     * @param array<string, SourceConfig> $sources
     * @param array<string, SinkConfig> $sinks
     */
    private function __construct(array $sources, array $sinks, string $baseDir)
    {
        $this->sources = $sources;
        $this->sinks = $sinks;
        $this->baseDir = $baseDir;
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read config file: {$path}");
        }

        try {
            $data = Toml::decode($contents, asArray: true);
        } catch (TomlError $error) {
            throw new RuntimeException("Invalid TOML in {$path}:\n{$error->getMessage()}");
        }

        if (!is_array($data)) {
            throw new RuntimeException('Invalid config at root: expected a table.');
        }

        $sources = $data['sources'] ?? [];
        $sinks = $data['sinks'] ?? [];

        if (!is_array($sources)) {
            throw new RuntimeException('Invalid config at sources: expected a table.');
        }

        if (!is_array($sinks)) {
            throw new RuntimeException('Invalid config at sinks: expected a table.');
        }

        self::assertSchema($sources, self::sourcesSchema(), 'sources');
        self::assertSchema($sinks, self::sinksSchema(), 'sinks');

        $baseDir = dirname($path);
        /** @var array<string, RawSourceConfig> $sources */
        $normalizedSources = self::normalizeSources($sources, $baseDir);
        /** @var array<string, RawSinkConfig> $sinks */
        $normalizedSinks = self::normalizeSinks($sinks, $normalizedSources, $baseDir);

        return new self($normalizedSources, $normalizedSinks, $baseDir);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function assertSchema(array $value, Validatable $validator, string $path): void
    {
        try {
            $validator->setName($path)->assert($value);
        } catch (NestedValidationException $error) {
            throw new RuntimeException("Invalid config at {$path}:\n{$error->getFullMessage()}");
        }
    }

    private static function sourcesSchema(): Validatable
    {
        return Validator::arrayType()->each(self::sourceSchema());
    }

    private static function sourceSchema(): Validatable
    {
        return Validator::arrayType()->keySet(
            Validator::key('type', Validator::stringType()->notEmpty()->equals('file')),
            Validator::key('dir', Validator::stringType()->notEmpty()),
            Validator::key('max_bytes', Validator::intType()->positive(), false),
            Validator::key('done_suffix', Validator::stringType()->notEmpty(), false),
        );
    }

    private static function sinksSchema(): Validatable
    {
        return Validator::arrayType()->each(self::sinkSchema());
    }

    private static function sinkSchema(): Validatable
    {
        return Validator::arrayType()->keySet(
            Validator::key('type', Validator::stringType()->notEmpty()->in(['file', 's3'])),
            Validator::key('dir', Validator::stringType()->notEmpty(), false),
            Validator::key('inputs', Validator::arrayType()->notEmpty()->each(Validator::stringType()->notEmpty())),
            Validator::key('prefix', Validator::stringType()->notEmpty(), false),
            Validator::key('format', Validator::stringType()->notEmpty()->equals('ndjson'), false),
            Validator::key('compression', Validator::stringType()->notEmpty()->equals('gzip'), false),
            Validator::key('bucket', Validator::stringType()->notEmpty(), false),
            Validator::key('region', Validator::stringType()->notEmpty(), false),
            Validator::key('endpoint', Validator::stringType()->notEmpty(), false),
            Validator::key('use_path_style_endpoint', Validator::boolType(), false),
            Validator::key(
                'credentials',
                Validator::arrayType()->keySet(
                    Validator::key('access_key_id', Validator::stringType()->notEmpty()),
                    Validator::key('secret_access_key', Validator::stringType()->notEmpty()),
                    Validator::key('session_token', Validator::stringType()->notEmpty(), false),
                ),
                false,
            ),
            Validator::key(
                'batch',
                Validator::arrayType()->keySet(
                    Validator::key('max_bytes', Validator::intType()->positive()),
                    Validator::key('max_wait_seconds', Validator::intType()->positive()),
                ),
                false,
            ),
        );
    }

    /**
     * @param array<string, RawSourceConfig> $sources
     * @return array<string, SourceConfig>
     */
    private static function normalizeSources(array $sources, string $baseDir): array
    {
        $normalized = [];

        foreach ($sources as $id => $source) {
            $normalizedSource = [
                'type' => 'file',
                'dir' => self::resolvePath($source['dir'], $baseDir),
                'max_bytes' => $source['max_bytes'] ?? null,
            ];
            if (array_key_exists('done_suffix', $source)) {
                $normalizedSource['done_suffix'] = $source['done_suffix'];
            }
            $normalized[$id] = $normalizedSource;
        }

        return $normalized;
    }

    /**
     * @param array<string, RawSinkConfig> $sinks
     * @param array<string, SourceConfig> $sources
     * @return array<string, SinkConfig>
     */
    private static function normalizeSinks(array $sinks, array $sources, string $baseDir): array
    {
        $normalized = [];

        foreach ($sinks as $id => $sink) {
            $type = $sink['type'];
            if ($type === '') {
                throw new RuntimeException("Invalid config at sinks.{$id}.type: type is required");
            }
            if ($type !== 'file' && $type !== 's3') {
                throw new RuntimeException("Unsupported sink type: {$type}");
            }

            $prefix = $sink['prefix'] ?? '';
            $format = $sink['format'] ?? 'ndjson';
            $compression = $sink['compression'] ?? null;
            $batch = $sink['batch'] ?? null;
            $batchMaxBytes = null;
            $batchMaxWaitSeconds = null;
            if ($batch !== null) {
                $batchMaxBytes = $batch['max_bytes'];
                $batchMaxWaitSeconds = $batch['max_wait_seconds'];
            }

            $inputs = $sink['inputs'];

            foreach ($inputs as $inputId) {
                if (array_key_exists($inputId, $sources)) {
                    continue;
                }

                throw new RuntimeException("Unknown source referenced by sinks.{$id}.inputs: {$inputId}");
            }

            if ($type === 'file') {
                $dir = $sink['dir'] ?? '';
                if ($dir === '') {
                    throw new RuntimeException("Invalid config at sinks.{$id}.dir: dir is required");
                }
                $resolvedDir = self::resolvePath($dir, $baseDir);
                $normalizedSink = [
                    'type' => 'file',
                    'inputs' => $inputs,
                    'format' => $format,
                    'compression' => $compression,
                    'batch' => $batch,
                    'batch_max_bytes' => $batchMaxBytes,
                    'batch_max_wait_seconds' => $batchMaxWaitSeconds,
                    'buffer_enabled' => $batchMaxBytes !== null,
                    'dir' => $dir,
                    'path' => self::buildDatedUniquePath($resolvedDir, $prefix, $format, $compression),
                ];
            } else {
                $bucket = $sink['bucket'] ?? '';
                if ($bucket === '') {
                    throw new RuntimeException("Invalid config at sinks.{$id}.bucket: bucket is required");
                }
                $normalizedSink = [
                    'type' => 's3',
                    'inputs' => $inputs,
                    'format' => $format,
                    'compression' => $compression,
                    'batch' => $batch,
                    'batch_max_bytes' => $batchMaxBytes,
                    'batch_max_wait_seconds' => $batchMaxWaitSeconds,
                    'buffer_enabled' => $batchMaxBytes !== null,
                    'bucket' => $bucket,
                    'region' => $sink['region'] ?? null,
                    'endpoint' => $sink['endpoint'] ?? null,
                    'use_path_style_endpoint' => $sink['use_path_style_endpoint'] ?? false,
                    'credentials' => $sink['credentials'] ?? null,
                ];
            }

            if ($prefix !== '' || array_key_exists('prefix', $sink)) {
                $normalizedSink['prefix'] = $prefix;
            }

            $normalized[$id] = $normalizedSink;
        }

        return $normalized;
    }

    private static function resolvePath(string $path, string $baseDir): string
    {
        if ($path === '') {
            return $baseDir;
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        return rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    private static function buildDatedUniquePath(
        string $dir,
        string $prefix,
        string $format,
        ?string $compression,
    ): string {
        $date = date('Ymd-His');
        $normalizedPrefix = trim($prefix);
        $extension = self::formatExtension($format, $compression);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = bin2hex(random_bytes(3));
            $filename = $date . '-' . $suffix;

            if ($normalizedPrefix !== '') {
                $filename = $normalizedPrefix . '-' . $filename;
            }

            if ($extension !== '') {
                $filename .= '.' . $extension;
            }

            $candidate = $dir . DIRECTORY_SEPARATOR . $filename;

            if (!file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Failed to generate a unique sink path.');
    }

    private static function formatExtension(string $format, ?string $compression): string
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
}
