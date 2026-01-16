<?php

declare(strict_types=1);

namespace App;

use Amp\File;
use App\Sink\SinkDriverRegistry;
use Revolt\EventLoop;
use RuntimeException;

use function Amp\async;

final class Application
{
    private const DEFAULT_DONE_SUFFIX = '.done';
    private const DEFAULT_POLL_INTERVAL_SECONDS = 1.0;

    /**
     * @var array<string, array{dev:int, ino:int, offset:int}>
     */
    private array $fileStates = [];

    /**
     * @var array<string, array{
     *   handle:?Amp\File\File,
     *   path:?string,
     *   size:int,
     *   last_append_at:float,
     *   timer_id:?string,
     *   sink:array<string, mixed>,
     *   driver:\App\Sink\SinkDriver,
     *   max_bytes:int,
     *   max_wait_seconds:int
     * }>
     */
    private array $buffers = [];

    private SinkDriverRegistry $sinkDrivers;

    public function __construct(
        protected Config $config,
        ?SinkDriverRegistry $sinkDrivers = null,
    ) {
        $this->sinkDrivers = $sinkDrivers ?? SinkDriverRegistry::withDefaults();
    }

    /**
     * @param array<int, array<string, mixed>> $sinks
     */
    private function enqueueRead(string $filePath, ?int $maxBytes, array $sinks): void
    {
        async(function () use ($filePath, $maxBytes, $sinks): void {
            if (!is_file($filePath)) {
                return;
            }

            $stat = $this->getFileStat($filePath);
            if ($stat === null) {
                $this->debug("stat failed path={$filePath}");
                return;
            }

            $state = $this->fileStates[$filePath] ?? null;
            $offset = $state['offset'] ?? 0;

            if ($stat['size'] < $offset) {
                $this->debug(
                    "size shrink path={$filePath} size={$stat['size']} offset={$offset} dev={$stat['dev']} ino={$stat['ino']}",
                );
                $offset = 0;
            }

            if ($stat['size'] === $offset) {
                $this->debug(
                    "skip no new data path={$filePath} size={$stat['size']} offset={$offset} dev={$stat['dev']} ino={$stat['ino']}",
                );
                $this->fileStates[$filePath] = [
                    'dev' => $stat['dev'],
                    'ino' => $stat['ino'],
                    'offset' => $offset,
                ];
                return;
            }

            $uniqueSinks = [];
            foreach ($sinks as $sink) {
                $type = $sink['type'] ?? '';
                if (!is_string($type) || $type === '') {
                    throw new RuntimeException('Sink type is required.');
                }
                $driver = $this->sinkDrivers->get($type);
                $key = $driver->uniqueKey($sink);
                $uniqueSinks[$key] = [
                    'driver' => $driver,
                    'sink' => $sink,
                ];
            }

            if ($uniqueSinks === []) {
                return;
            }

            try {
                $input = File\openFile($filePath, 'r');
            } catch (Throwable) {
                return;
            }

            $outputs = [];
            foreach ($uniqueSinks as $entry) {
                $sink = $entry['sink'];
                $driver = $entry['driver'];
                try {
                    $driver->prepare($sink);
                    $batchMaxBytes = $sink['batch_max_bytes'] ?? null;
                    $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'] ?? null;
                    $bufferingEnabled = $batchMaxBytes !== null && $batchMaxWaitSeconds !== null;
                    $outputs[] = [
                        'driver' => $driver,
                        'sink' => $sink,
                        'writer' => $bufferingEnabled ? null : $driver->openWriter($sink),
                        'batch_max_bytes' => $batchMaxBytes,
                        'batch_max_wait_seconds' => $batchMaxWaitSeconds,
                    ];
                } catch (Throwable) {
                    $input->close();
                    foreach ($outputs as $output) {
                        if ($output['writer'] === null) {
                            continue;
                        }

                        $output['writer']->close();
                    }
                    return;
                }
            }

            if ($offset > 0) {
                $input->seek($offset);
                $this->debug("seek path={$filePath} offset={$offset}");
            }

            $buffer = '';
            while (($chunk = $input->read()) !== null) {
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos + 1);
                    $buffer = substr($buffer, $pos + 1);
                    $this->writeLine($outputs, $line, $maxBytes);
                }
            }

            if ($buffer !== '') {
                $this->writeLine($outputs, $buffer, $maxBytes);
            }

            $newOffset = $input->tell();

            $input->close();
            foreach ($outputs as $output) {
                if ($output['writer'] === null) {
                    continue;
                }

                $output['writer']->close();
            }

            $this->fileStates[$filePath] = [
                'dev' => $stat['dev'],
                'ino' => $stat['ino'],
                'offset' => $newOffset,
            ];
            $this->debug(
                "update offset path={$filePath} new_offset={$newOffset} size={$stat['size']} dev={$stat['dev']} ino={$stat['ino']}",
            );
        })->await();
    }

    private function debug(string $message): void
    {
        if (getenv('PHLUENT_DEBUG') !== '1') {
            return;
        }

        fwrite(STDERR, "[phluent] {$message}" . PHP_EOL);
    }

    private function getFileStat(string $path): ?array
    {
        $previous = set_error_handler(static function (int $type, string $message): void {
            throw new RuntimeException($message);
        });

        try {
            $stat = stat($path);
        } catch (RuntimeException) {
            $stat = false;
        } finally {
            restore_error_handler();
            if ($previous !== null) {
                set_error_handler($previous);
            }
        }

        if ($stat === false) {
            return null;
        }

        return $stat;
    }

    /**
     * @param array<int, array{
     *   driver:\App\Sink\SinkDriver,
     *   sink:array<string, mixed>,
     *   writer:?\App\Sink\SinkWriter,
     *   batch_max_bytes:?int,
     *   batch_max_wait_seconds:?int
     * }> $outputs
     */
    private function writeLine(array $outputs, string $line, ?int $maxBytes): void
    {
        if ($maxBytes !== null) {
            $trimmed = rtrim($line, "\r\n");
            if (strlen($trimmed) > $maxBytes) {
                return;
            }
        }

        foreach ($outputs as $output) {
            $formatted = $output['driver']->formatLine($line, $output['sink']);
            if ($formatted === null) {
                continue;
            }
            $batchMaxBytes = $output['batch_max_bytes'] ?? null;
            $batchMaxWaitSeconds = $output['batch_max_wait_seconds'] ?? null;
            if ($batchMaxBytes !== null && $batchMaxWaitSeconds !== null) {
                $this->bufferLine($output, $formatted);
                continue;
            }
            if ($output['writer'] !== null) {
                $output['writer']->write($formatted);
            }
        }
    }

    /**
     * @param array{
     *   driver:\App\Sink\SinkDriver,
     *   sink:array<string, mixed>,
     *   batch_max_bytes:int,
     *   batch_max_wait_seconds:int
     * } $output
     */
    private function bufferLine(array $output, string $data): void
    {
        $sinkKey = $output['driver']->uniqueKey($output['sink']);
        $buffer = $this->buffers[$sinkKey] ?? null;
        if ($buffer === null || $buffer['handle'] === null) {
            $buffer = $this->createBufferState($output);
        }

        $buffer['handle']->write($data);
        $buffer['size'] += strlen($data);
        $buffer['last_append_at'] = microtime(true);
        $this->buffers[$sinkKey] = $buffer;

        $this->scheduleBufferFlush($sinkKey, $buffer['max_wait_seconds'], $buffer['last_append_at']);

        if ($buffer['size'] >= $buffer['max_bytes']) {
            $this->flushBuffer($sinkKey);
        }
    }

    /**
     * @param array{driver:\App\Sink\SinkDriver, sink:array<string, mixed>, batch_max_bytes:int, batch_max_wait_seconds:int} $output
     * @return array{handle:Amp\File\File, path:string, size:int, last_append_at:float, timer_id:?string, sink:array<string, mixed>, driver:\App\Sink\SinkDriver, max_bytes:int, max_wait_seconds:int}
     */
    private function createBufferState(array $output): array
    {
        $path = $this->createTempBufferPath();
        $handle = File\openFile($path, 'c+');

        return [
            'handle' => $handle,
            'path' => $path,
            'size' => 0,
            'last_append_at' => 0.0,
            'timer_id' => null,
            'sink' => $output['sink'],
            'driver' => $output['driver'],
            'max_bytes' => $output['batch_max_bytes'],
            'max_wait_seconds' => $output['batch_max_wait_seconds'],
        ];
    }

    private function createTempBufferPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phluent-batch-');
        if ($path === false) {
            throw new RuntimeException('Failed to create temp buffer file');
        }

        return $path;
    }

    private function scheduleBufferFlush(string $sinkKey, int $waitSeconds, float $lastAppendAt): void
    {
        if ($waitSeconds <= 0) {
            return;
        }

        $buffer = $this->buffers[$sinkKey] ?? null;
        if ($buffer === null) {
            return;
        }

        if ($buffer['timer_id'] !== null) {
            EventLoop::cancel($buffer['timer_id']);
        }

        $timerId = EventLoop::delay($waitSeconds, function () use ($sinkKey, $lastAppendAt): void {
            $current = $this->buffers[$sinkKey] ?? null;
            if ($current === null || $current['size'] === 0) {
                return;
            }
            if ($current['last_append_at'] !== $lastAppendAt) {
                return;
            }
            $this->flushBuffer($sinkKey);
        });

        $this->buffers[$sinkKey]['timer_id'] = $timerId;
    }

    private function flushBuffer(string $sinkKey): void
    {
        $buffer = $this->buffers[$sinkKey] ?? null;
        if ($buffer === null || $buffer['size'] === 0 || $buffer['handle'] === null || $buffer['path'] === null) {
            return;
        }

        if ($buffer['timer_id'] !== null) {
            EventLoop::cancel($buffer['timer_id']);
        }

        $buffer['handle']->seek(0);
        $writer = $buffer['driver']->openWriter($buffer['sink']);
        try {
            while (($chunk = $buffer['handle']->read()) !== null) {
                $writer->write($chunk);
            }
        } finally {
            $writer->close();
            $buffer['handle']->close();
            $this->safeUnlink($buffer['path']);
        }

        $this->buffers[$sinkKey] = [
            'handle' => null,
            'path' => null,
            'size' => 0,
            'last_append_at' => 0.0,
            'timer_id' => null,
            'sink' => $buffer['sink'],
            'driver' => $buffer['driver'],
            'max_bytes' => $buffer['max_bytes'],
            'max_wait_seconds' => $buffer['max_wait_seconds'],
        ];
    }

    private function safeUnlink(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        $previous = set_error_handler(static function (int $type, string $message): void {
            throw new RuntimeException($message);
        });

        try {
            unlink($path);
        } catch (RuntimeException) {
            return;
        } finally {
            restore_error_handler();
            if ($previous !== null) {
                set_error_handler($previous);
            }
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildSourceSinkMap(Config $config): array
    {
        $map = [];

        foreach ($config->sinks as $sink) {
            foreach ($sink['inputs'] as $input) {
                $map[$input][] = $sink;
            }
        }

        return $map;
    }

    public function run(): void
    {
        if ($this->config->sinks === []) {
            throw new RuntimeException('No sinks configured');
        }

        $sourceToSinks = $this->buildSourceSinkMap($this->config);
        if ($sourceToSinks === []) {
            throw new RuntimeException('No sources connected to sinks');
        }

        $fileSources = [];

        foreach ($this->config->sources as $id => $source) {
            if (!array_key_exists($id, $sourceToSinks)) {
                continue;
            }

            $fileSources[$id] = $source;
        }

        if ($fileSources === []) {
            throw new RuntimeException('No file sources configured');
        }

        if ($this->supportsInotify()) {
            $this->runWithInotify($fileSources, $sourceToSinks);
            return;
        }

        $this->runWithPolling($fileSources, $sourceToSinks);
    }

    /**
     * @param array<string, array<string, mixed>> $fileSources
     * @param array<string, array<int, array<string, mixed>>> $sourceToSinks
     */
    private function runWithInotify(array $fileSources, array $sourceToSinks): void
    {
        $fd = inotify_init();

        if ($fd === false) {
            $this->debug('Init inotify failed, falling back to polling');
            $this->runWithPolling($fileSources, $sourceToSinks);
            return;
        }

        stream_set_blocking($fd, false);

        $fileContexts = [];

        foreach ($fileSources as $id => $source) {
            $watchDir = $source['dir'] ?? '';
            if (!is_string($watchDir) || $watchDir === '') {
                throw new RuntimeException("Watch directory missing for source: {$id}");
            }

            if (!is_dir($watchDir)) {
                throw new RuntimeException("Watch directory not found: {$watchDir}");
            }

            $maxBytes = $source['max_bytes'] ?? null;

            $watchId = inotify_add_watch($fd, $watchDir, IN_CLOSE_WRITE | IN_MOVED_TO);
            if ($watchId === false) {
                throw new RuntimeException("Failed to add inotify watch for: {$watchDir}");
            }

            $fileContexts[$watchId] = [
                'dir' => $watchDir,
                'max_bytes' => is_int($maxBytes) ? $maxBytes : null,
                'sinks' => $sourceToSinks[$id],
            ];
        }

        EventLoop::onReadable($fd, function ($callbackId, $fd) use ($fileContexts): void {
            $events = inotify_read($fd);

            if ($events === false) {
                throw new RuntimeException('Events must not false, should be an array contain multiple event');
            }

            /** @var array{wd:int,mask:int,cookie:int,name:string} $event */
            foreach ($events as $event) {
                $context = $fileContexts[$event['wd']] ?? null;
                if ($context === null) {
                    continue;
                }

                $file_path = $context['dir'] . DIRECTORY_SEPARATOR . $event['name'];
                $this->enqueueRead($file_path, $context['max_bytes'], $context['sinks']);
            }
        });

        EventLoop::run();
    }

    /**
     * @param array<string, array<string, mixed>> $fileSources
     * @param array<string, array<int, array<string, mixed>>> $sourceToSinks
     */
    private function runWithPolling(array $fileSources, array $sourceToSinks): void
    {
        $fileContexts = [];

        foreach ($fileSources as $id => $source) {
            $watchDir = $source['dir'] ?? '';
            if (!is_string($watchDir) || $watchDir === '') {
                throw new RuntimeException("Watch directory missing for source: {$id}");
            }

            if (!is_dir($watchDir)) {
                throw new RuntimeException("Watch directory not found: {$watchDir}");
            }

            $maxBytes = $source['max_bytes'] ?? null;
            $doneSuffix = $source['done_suffix'] ?? self::DEFAULT_DONE_SUFFIX;

            if (!is_string($doneSuffix) || $doneSuffix === '') {
                $doneSuffix = self::DEFAULT_DONE_SUFFIX;
            }

            $fileContexts[] = [
                'dir' => $watchDir,
                'max_bytes' => is_int($maxBytes) ? $maxBytes : null,
                'sinks' => $sourceToSinks[$id],
                'done_suffix' => $doneSuffix,
            ];
        }

        $poll = function (string $callbackId) use ($fileContexts): void {
            foreach ($fileContexts as $context) {
                $iterator = new \FilesystemIterator($context['dir'], \FilesystemIterator::SKIP_DOTS);
                foreach ($iterator as $entry) {
                    if (!$entry->isFile()) {
                        continue;
                    }
                    $name = $entry->getFilename();
                    if (!str_ends_with($name, $context['done_suffix'])) {
                        continue;
                    }
                    $this->enqueueRead($entry->getPathname(), $context['max_bytes'], $context['sinks']);
                }
            }
        };

        EventLoop::defer($poll);
        EventLoop::repeat(self::DEFAULT_POLL_INTERVAL_SECONDS, $poll);
        EventLoop::run();
    }

    private function supportsInotify(): bool
    {
        return PHP_OS_FAMILY === 'Linux' && extension_loaded('inotify');
    }
}
