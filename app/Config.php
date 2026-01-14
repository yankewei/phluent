<?php

declare(strict_types=1);

namespace App;

use Devium\Toml\Toml;
use Devium\Toml\TomlError;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator;
use RuntimeException;

final class Config
{
    /**
     * Validated and normalized source config entries from Config::load().
     *
     * @var array<string, array{
     *   type:string,
     *   dir:string,
     *   max_bytes:?int
     * }>
     */
    public readonly array $sources;

    /**
     * Validated and normalized sink config entries from Config::load().
     *
     * @var array<string, array{
     *   type:string,
     *   dir:string,
     *   inputs:array<int, string>,
     *   prefix?:string,
     *   format:string,
     *   compression?:?string,
     *   batch?:?array{max_bytes:int, max_wait_seconds:int},
     *   path:string,
     *   batch_max_bytes:?int,
     *   batch_max_wait_seconds:?int,
     *   buffer_enabled:bool
     * }>
     */
    public readonly array $sinks;

    /**
     * Base directory used to resolve relative paths from the config file location.
     */
    public readonly string $baseDir;

    /**
     * @param array<string, array{type:string, dir:string, max_bytes:?int}> $sources
     * @param array<string, array{
     *   type:string,
     *   dir:string,
     *   inputs:array<int, string>,
     *   prefix?:string,
     *   format:string,
     *   compression?:?string,
     *   batch?:?array{max_bytes:int, max_wait_seconds:int},
     *   path:string,
     *   batch_max_bytes:?int,
     *   batch_max_wait_seconds:?int,
     *   buffer_enabled:bool
     * }> $sinks
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

        $sources = $data['sources'] ?? [];
        $sinks = $data['sinks'] ?? [];

        self::assertSchema($sources, self::sourcesSchema(), 'sources');
        self::assertSchema($sinks, self::sinksSchema(), 'sinks');

        $baseDir = dirname($path);
        $normalizedSources = self::normalizeSources($sources, $baseDir);
        $normalizedSinks = self::normalizeSinks($sinks, $normalizedSources, $baseDir);

        return new self($normalizedSources, $normalizedSinks, $baseDir);
    }

    private static function assertSchema(array $value, Validator $validator, string $path): void
    {
        try {
            $validator->setName($path)->assert($value);
        } catch (NestedValidationException $error) {
            throw new RuntimeException("Invalid config at {$path}:\n{$error->getFullMessage()}");
        }
    }

    private static function sourcesSchema(): Validator
    {
        return Validator::arrayType()->each(self::sourceSchema());
    }

    private static function sourceSchema(): Validator
    {
        return Validator::arrayType()->keySet(
            Validator::key('type', Validator::stringType()->notEmpty()->equals('file')),
            Validator::key('dir', Validator::stringType()->notEmpty()),
            Validator::key('max_bytes', Validator::intType()->positive(), false),
        );
    }

    private static function sinksSchema(): Validator
    {
        return Validator::arrayType()->each(self::sinkSchema());
    }

    private static function sinkSchema(): Validator
    {
        return Validator::arrayType()->keySet(
            Validator::key('type', Validator::stringType()->notEmpty()->equals('file')),
            Validator::key('dir', Validator::stringType()->notEmpty()),
            Validator::key('inputs', Validator::arrayType()->notEmpty()->each(Validator::stringType()->notEmpty())),
            Validator::key('prefix', Validator::stringType()->notEmpty(), false),
            Validator::key('format', Validator::stringType()->notEmpty()->equals('ndjson'), false),
            Validator::key('compression', Validator::stringType()->notEmpty()->equals('gzip'), false),
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
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeSources(array $sources, string $baseDir): array
    {
        $normalized = [];

        foreach ($sources as $id => $source) {
            $normalized[$id] = $source;
            $normalized[$id]['dir'] = self::resolvePath($source['dir'], $baseDir);
            $normalized[$id]['max_bytes'] = $source['max_bytes'] ?? null;
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeSinks(array $sinks, array $sources, string $baseDir): array
    {
        $normalized = [];

        foreach ($sinks as $id => $sink) {
            $dir = self::resolvePath($sink['dir'], $baseDir);
            $prefix = $sink['prefix'] ?? '';
            $format = $sink['format'] ?? 'ndjson';
            $compression = $sink['compression'] ?? null;
            $batch = $sink['batch'] ?? null;
            $batchMaxBytes = null;
            $batchMaxWaitSeconds = null;
            if ($batch !== null) {
                $batchMaxBytes = $batch['max_bytes'] ?? null;
                $batchMaxWaitSeconds = $batch['max_wait_seconds'] ?? null;
                if ($batchMaxBytes === null || $batchMaxWaitSeconds === null) {
                    throw new RuntimeException(
                        "Invalid config at sinks.{$id}.batch: max_bytes and max_wait_seconds must be set together",
                    );
                }
            }

            $sink['path'] = self::buildDatedUniquePath($dir, $prefix, $format, $compression);
            $sink['format'] = $format;
            $inputs = $sink['inputs'];

            foreach ($inputs as $inputId) {
                if (!array_key_exists($inputId, $sources)) {
                    throw new RuntimeException("Unknown source referenced by sinks.{$id}.inputs: {$inputId}");
                }
            }

            $normalized[$id] = $sink;
            $normalized[$id]['type'] = $sink['type'];
            $normalized[$id]['inputs'] = $inputs;
            $normalized[$id]['format'] = $format;
            $normalized[$id]['compression'] = $compression;
            $normalized[$id]['batch'] = $batch;
            $normalized[$id]['batch_max_bytes'] = $batchMaxBytes;
            $normalized[$id]['batch_max_wait_seconds'] = $batchMaxWaitSeconds;
            $normalized[$id]['buffer_enabled'] = $batchMaxBytes !== null;
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
