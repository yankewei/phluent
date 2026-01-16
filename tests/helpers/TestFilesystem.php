<?php

declare(strict_types=1);

final class TestFilesystem
{
    public static function createTempDir(string $prefix = 'phluent-test-'): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $path = $base . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4));

        if (!mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new RuntimeException("Failed to create temp dir: {$path}");
        }

        return $path;
    }

    public static function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }

        $bytes = file_put_contents($path, $contents);
        if ($bytes === false) {
            throw new RuntimeException("Failed to write file: {$path}");
        }
    }

    public static function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }

        return $contents;
    }

    public static function fixturePath(string $relative): string
    {
        return __DIR__ . '/../fixtures/' . ltrim($relative, '/');
    }

    public static function copyFixture(string $fixtureRelative, string $destination): void
    {
        $source = self::fixturePath($fixtureRelative);
        if (!is_file($source)) {
            throw new RuntimeException("Fixture not found: {$source}");
        }

        $contents = self::readFile($source);
        self::writeFile($destination, $contents);
    }

    public static function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                self::removeDir($full);
                continue;
            }

            unlink($full);
        }

        rmdir($path);
    }
}
