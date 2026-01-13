<?php

declare(strict_types=1);

namespace App\Sink;

use RuntimeException;

final class SinkDriverRegistry
{
    /**
     * @var array<string, SinkDriver>
     */
    private array $drivers = [];

    /**
     * @param array<int, SinkDriver> $drivers
     */
    public function __construct(array $drivers = [])
    {
        foreach ($drivers as $driver) {
            $this->register($driver);
        }
    }

    public static function withDefaults(): self
    {
        return new self([
            new FileSinkDriver(),
        ]);
    }

    public function register(SinkDriver $driver): void
    {
        $this->drivers[$driver->type()] = $driver;
    }

    public function get(string $type): SinkDriver
    {
        $driver = $this->drivers[$type] ?? null;
        if ($driver === null) {
            throw new RuntimeException("Unsupported sink type: {$type}");
        }

        return $driver;
    }
}
