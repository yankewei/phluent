<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigS3Test extends TestCase
{
    public function testS3SinkRequiresBucket(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $contents = <<<TOML
        [sources.main]
        type = "file"
        dir = "input"

        [sinks.main]
        type = "s3"
        inputs = ["main"]
        TOML;

        try {
            $configPath = ConfigFactory::writeConfig($contents, $baseDir, 'missing-bucket.toml');

            ExceptionAssertions::assertRuntimeExceptionMessageContains(
                $this,
                static fn(): Config => Config::load($configPath),
                'bucket is required',
            );
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }

    public function testS3DefaultsPathStyleToFalse(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        $contents = <<<TOML
        [sources.main]
        type = "file"
        dir = "input"

        [sinks.main]
        type = "s3"
        bucket = "example"
        inputs = ["main"]
        TOML;

        try {
            $configPath = ConfigFactory::writeConfig($contents, $baseDir, 'default-path-style.toml');

            $config = Config::load($configPath);
            $this->assertFalse($config->sinks['main']['use_path_style_endpoint']);
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
