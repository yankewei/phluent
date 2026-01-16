<?php

declare(strict_types=1);

use App\Config;

final class ConfigFactory
{
    public static function writeConfigFromFixture(
        string $fixtureRelative,
        string $dir,
        string $filename = 'config.toml',
    ): string {
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        TestFilesystem::copyFixture($fixtureRelative, $path);

        return $path;
    }

    public static function writeConfig(string $contents, string $dir, string $filename = 'config.toml'): string
    {
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        TestFilesystem::writeFile($path, $contents);

        return $path;
    }

    public static function loadValidConfig(string $dir, string $filename = 'config.toml'): Config
    {
        $path = self::writeConfigFromFixture('configs/valid.toml', $dir, $filename);
        return Config::load($path);
    }
}
