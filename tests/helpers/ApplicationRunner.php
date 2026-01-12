<?php

declare(strict_types=1);

final class ApplicationRunner
{
    /**
     * @param array<int, array{path:string, format:string, compression:?string}> $sinks
     */
    public static function enqueueRead(Application $app, string $filePath, ?int $maxBytes, array $sinks): void
    {
        $method = new ReflectionMethod($app, 'enqueueRead');
        $method->setAccessible(true);
        $method->invoke($app, $filePath, $maxBytes, $sinks);
    }
}
