<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigPathTest extends TestCase
{
    public function testResolvesRelativePathsFromConfigDir(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $contents = <<<TOML
        [sources.main]
        type = "file"
        dir = "input"

        [sinks.main]
        type = "file"
        dir = "output"
        prefix = "app"
        format = "ndjson"
        inputs = ["main"]
        TOML;

        try {
            $configPath = ConfigFactory::writeConfig($contents, $baseDir, 'paths.toml');
            $config = Config::load($configPath);

            $this->assertStringStartsWith($baseDir . DIRECTORY_SEPARATOR . 'input', $config->sources['main']['dir']);
            $this->assertStringStartsWith($baseDir . DIRECTORY_SEPARATOR . 'output', $config->sinks['main']['path']);
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
