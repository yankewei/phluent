<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaTest extends TestCase
{
    public function testMissingSourceDirFailsSchema(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        try {
            $configPath = ConfigFactory::writeConfigFromFixture('configs/invalid.toml', $baseDir);

            ExceptionAssertions::assertRuntimeExceptionMessageContains(
                $this,
                static fn(): Config => Config::load($configPath),
                'Invalid config at sources',
            );
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }

    public function testUnknownSinkInputIsRejected(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $contents = <<<TOML
        [sources.main]
        type = "file"
        dir = "input"

        [sinks.main]
        type = "file"
        dir = "output"
        inputs = ["missing"]
        TOML;

        try {
            $configPath = ConfigFactory::writeConfig($contents, $baseDir, 'bad-input.toml');

            ExceptionAssertions::assertRuntimeExceptionMessageContains(
                $this,
                static fn(): Config => Config::load($configPath),
                'Unknown source referenced',
            );
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
