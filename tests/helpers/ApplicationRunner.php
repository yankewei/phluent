<?php

declare(strict_types=1);

use App\Application;

final class ApplicationRunner
{
    /**
     * @param array<int, array<string, mixed>> $sinks
     */
    public static function enqueueRead(Application $app, string $filePath, ?int $maxBytes, array $sinks): void
    {
        $method = new ReflectionMethod($app, 'enqueueRead');
        $method->setAccessible(true);
        $method->invoke($app, $filePath, $maxBytes, $sinks);
    }
}
