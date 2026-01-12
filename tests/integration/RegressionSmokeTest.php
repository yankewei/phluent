<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegressionSmokeTest extends TestCase
{
    public function testTestRunnerBootstrapsProjectClasses(): void
    {
        $this->assertTrue(class_exists(Application::class));
        $this->assertTrue(class_exists(Config::class));
    }
}
