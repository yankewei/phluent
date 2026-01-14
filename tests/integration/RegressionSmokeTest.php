<?php

declare(strict_types=1);

use App\Application;
use App\Config;
use App\Sink\FileSinkDriver;
use App\Sink\SinkDriverRegistry;
use PHPUnit\Framework\TestCase;

final class RegressionSmokeTest extends TestCase
{
    public function testTestRunnerBootstrapsProjectClasses(): void
    {
        $this->assertTrue(class_exists(Application::class));
        $this->assertTrue(class_exists(Config::class));
        $this->assertTrue(class_exists(FileSinkDriver::class));
        $this->assertTrue(class_exists(SinkDriverRegistry::class));
    }
}
