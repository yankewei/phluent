<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigLoadErrorsTest extends TestCase
{
    public function testMissingConfigFileThrows(): void
    {
        $missingPath = TestFilesystem::createTempDir() . '/missing.toml';

        ExceptionAssertions::assertRuntimeExceptionMessageContains(
            $this,
            static fn(): Config => Config::load($missingPath),
            'Config file not found',
        );
    }

    public function testInvalidTomlThrows(): void
    {
        $baseDir = TestFilesystem::createTempDir();
        try {
            $configPath = ConfigFactory::writeConfig('invalid = [', $baseDir, 'invalid.toml');

            ExceptionAssertions::assertRuntimeExceptionMessageContains(
                $this,
                static fn(): Config => Config::load($configPath),
                'Invalid TOML',
            );
        } finally {
            TestFilesystem::removeDir($baseDir);
        }
    }
}
