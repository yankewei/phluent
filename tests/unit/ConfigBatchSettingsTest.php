<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigBatchSettingsTest extends TestCase
{
    public function testBatchSettingsRequirePair(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $contents = <<<TOML
[sources.main]
type = "file"
dir = "input"

[sinks.main]
type = "file"
dir = "output"
inputs = ["main"]

[sinks.main.batch]
max_bytes = 1024
TOML;

        try {
            $configPath = ConfigFactory::writeConfig($contents, $baseDir, 'bad-batch.toml');

            ExceptionAssertions::assertRuntimeExceptionMessageContains(
                $this,
                fn (): Config => Config::load($configPath),
                'Invalid config at sinks',
            );
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }

    public function testBatchSettingsLoadSuccessfully(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $contents = <<<TOML
[sources.main]
type = "file"
dir = "input"

[sinks.main]
type = "file"
dir = "output"
inputs = ["main"]

[sinks.main.batch]
max_bytes = 1024
max_wait_seconds = 5
TOML;

        try {
            $configPath = ConfigFactory::writeConfig($contents, $baseDir, 'batch.toml');

            $config = Config::load($configPath);
            $this->assertSame(1024, $config->sinks['main']['batch_max_bytes']);
            $this->assertSame(5, $config->sinks['main']['batch_max_wait_seconds']);
            $this->assertTrue($config->sinks['main']['buffer_enabled']);
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
