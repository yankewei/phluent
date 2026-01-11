<?php

declare(strict_types=1);

use Devium\Toml\Toml;
use Devium\Toml\TomlError;

final class Config
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $sources = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $sinks = [];

    public string $baseDir;

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
            throw new RuntimeException("Invalid TOML document in {$path}");
        }

        $config = new self();
        $config->baseDir = dirname($path);
        $config->sources = self::normalizeSources($data['sources'] ?? [], $config->baseDir);
        $config->sinks = self::normalizeSinks($data['sinks'] ?? [], $config->sources, $config->baseDir);

        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeSources(array $sources, string $baseDir): array
    {
        $normalized = [];

        foreach ($sources as $id => $source) {
            if (!is_array($source)) {
                throw new RuntimeException("Source definition must be a table: sources.{$id}");
            }

            $type = self::requireString($source, 'type', "sources.{$id}.type");
            $normalized[$id] = $source;
            $normalized[$id]['type'] = $type;

            if ($type === 'file') {
                $dir = self::requireString($source, 'dir', "sources.{$id}.dir");
                $normalized[$id]['dir'] = self::resolvePath($dir, $baseDir);
                $normalized[$id]['max_bytes'] = self::optionalInt($source, 'max_bytes', "sources.{$id}.max_bytes");
            } else {
                throw new RuntimeException("Unsupported source type: sources.{$id}.type must be 'file'");
            }
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
            if (!is_array($sink)) {
                throw new RuntimeException("Sink definition must be a table: sinks.{$id}");
            }

            $type = self::requireString($sink, 'type', "sinks.{$id}.type");
            if ($type !== 'file') {
                throw new RuntimeException("Unsupported sink type: sinks.{$id}.type must be 'file'");
            }
            $path = self::requireString($sink, 'path', "sinks.{$id}.path");
            $sink['path'] = self::resolvePath($path, $baseDir);
            $inputs = self::requireStringArray($sink, 'inputs', "sinks.{$id}.inputs");

            foreach ($inputs as $inputId) {
                if (!array_key_exists($inputId, $sources)) {
                    throw new RuntimeException("Unknown source referenced by sinks.{$id}.inputs: {$inputId}");
                }
            }

            $normalized[$id] = $sink;
            $normalized[$id]['type'] = $type;
            $normalized[$id]['inputs'] = $inputs;
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

    private static function requireString(array $data, string $key, string $path): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Invalid or missing string value: {$path}");
        }

        return $value;
    }

    private static function optionalInt(array $data, string $key, string $path): ?int
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if (!is_int($value) || $value <= 0) {
            throw new RuntimeException("Invalid integer value: {$path}");
        }

        return $value;
    }

    /**
     * @return string[]
     */
    private static function requireStringArray(array $data, string $key, string $path): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || $value === []) {
            throw new RuntimeException("Invalid or missing array value: {$path}");
        }

        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new RuntimeException("Array must contain non-empty strings: {$path}");
            }
        }

        return $value;
    }
}
