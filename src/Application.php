<?php

declare(strict_types=1);

use Amp\File;
use Revolt\EventLoop;

use function Amp\async;

final class Application
{
    /**
     * @var array<string, array{dev:int, ino:int, offset:int}>
     */
    private array $fileStates = [];

    /**
     * @var array<string, array{handle:?Amp\File\File, path:?string, size:int, last_append_at:float, timer_id:?string, sink_path:string, compression:?string, max_bytes:int, max_wait_seconds:int}>
     */
    private array $buffers = [];

    /**
     * @param array<int, array{path:string, format:string, compression: ?string}> $sinks
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
                $path = $sink['path'] ?? null;
                $format = $sink['format'] ?? null;
                $compression = $sink['compression'] ?? null;
                $batchMaxBytes = $sink['batch_max_bytes'] ?? null;
                $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'] ?? null;

                if (!is_string($path) || $path === '' || !is_string($format) || $format === '') {
                    continue;
                }

                if ($compression !== null && !is_string($compression)) {
                    continue;
                }

                if (($batchMaxBytes === null) !== ($batchMaxWaitSeconds === null)) {
                    continue;
                }

                if ($batchMaxBytes !== null && (!is_int($batchMaxBytes) || $batchMaxBytes <= 0)) {
                    continue;
                }

                if ($batchMaxWaitSeconds !== null && (!is_int($batchMaxWaitSeconds) || $batchMaxWaitSeconds <= 0)) {
                    continue;
                }

                $key =
                    $path
                    . '|'
                    . $format
                    . '|'
                    . ($compression ?? '')
                    . '|'
                    . ($batchMaxBytes ?? '')
                    . '|'
                    . ($batchMaxWaitSeconds ?? '');
                $uniqueSinks[$key] = [
                    'path' => $path,
                    'format' => $format,
                    'compression' => $compression,
                    'batch_max_bytes' => $batchMaxBytes,
                    'batch_max_wait_seconds' => $batchMaxWaitSeconds,
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
            foreach ($uniqueSinks as $sink) {
                $sinkPath = $sink['path'];
                try {
                    $this->ensureSinkDirectory($sinkPath);
                    $batchMaxBytes = $sink['batch_max_bytes'] ?? null;
                    $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'] ?? null;
                    $bufferingEnabled = $batchMaxBytes !== null && $batchMaxWaitSeconds !== null;
                    $outputs[] = [
                        'writer' => $bufferingEnabled ? null : $this->openSinkWriter($sinkPath, $sink['compression']),
                        'format' => $sink['format'],
                        'path' => $sinkPath,
                        'compression' => $sink['compression'],
                        'batch_max_bytes' => $batchMaxBytes,
                        'batch_max_wait_seconds' => $batchMaxWaitSeconds,
                    ];
                } catch (Throwable) {
                    $input->close();
                    foreach ($outputs as $output) {
                        if ($output['writer'] !== null) {
                            $this->closeSinkWriter($output['writer']);
                        }
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
                if ($output['writer'] !== null) {
                    $this->closeSinkWriter($output['writer']);
                }
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
        $previous = set_error_handler(function (int $type, string $message): void {
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

    private function ensureSinkDirectory(string $sinkPath): void
    {
        $dir = dirname($sinkPath);
        if ($dir === '' || $dir === '.') {
            return;
        }

        File\createDirectoryRecursively($dir);
    }

    /**
     * @param array<int, array{writer:?array{type:string, handle:mixed}, format:string, path:string, compression:?string, batch_max_bytes:?int, batch_max_wait_seconds:?int}> $outputs
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
            $formatted = $this->formatLine($line, $output['format']);
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
                $this->writeToSinkWriter($output['writer'], $formatted);
            }
        }
    }

    /**
     * @param array{writer:?array{type:string, handle:mixed}, format:string, path:string, compression:?string, batch_max_bytes:int, batch_max_wait_seconds:int} $output
     */
    private function bufferLine(array $output, string $data): void
    {
        $sinkKey = $output['path'];
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
     * @param array{path:string, compression:?string, batch_max_bytes:int, batch_max_wait_seconds:int} $output
     * @return array{handle:Amp\File\File, path:string, size:int, last_append_at:float, timer_id:?string, sink_path:string, compression:?string, max_bytes:int, max_wait_seconds:int}
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
            'sink_path' => $output['path'],
            'compression' => $output['compression'],
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
        $writer = $this->openSinkWriter($buffer['sink_path'], $buffer['compression']);
        try {
            while (($chunk = $buffer['handle']->read()) !== null) {
                $this->writeToSinkWriter($writer, $chunk);
            }
        } finally {
            $this->closeSinkWriter($writer);
            $buffer['handle']->close();
            $this->safeUnlink($buffer['path']);
        }

        $this->buffers[$sinkKey] = [
            'handle' => null,
            'path' => null,
            'size' => 0,
            'last_append_at' => 0.0,
            'timer_id' => null,
            'sink_path' => $buffer['sink_path'],
            'compression' => $buffer['compression'],
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

    private function formatLine(string $line, string $format): ?string
    {
        if ($format === 'ndjson') {
            return $line;
        }

        throw new RuntimeException("Unsupported sink format: {$format}");
    }

    /**
     * @return array{type:string, handle:mixed}
     */
    private function openSinkWriter(string $path, ?string $compression): array
    {
        if ($compression === 'gzip') {
            if (!function_exists('gzopen')) {
                throw new RuntimeException('gzip compression requires the zlib extension.');
            }

            $handle = gzopen($path, 'ab');
            if ($handle === false) {
                throw new RuntimeException("Failed to open gzip sink: {$path}");
            }

            return [
                'type' => 'gzip',
                'handle' => $handle,
            ];
        }

        return [
            'type' => 'file',
            'handle' => File\openFile($path, 'a'),
        ];
    }

    /**
     * @param array{type:string, handle:mixed} $writer
     */
    private function writeToSinkWriter(array $writer, string $data): void
    {
        if ($writer['type'] === 'gzip') {
            gzwrite($writer['handle'], $data);
            return;
        }

        $writer['handle']->write($data);
    }

    /**
     * @param array{type:string, handle:mixed} $writer
     */
    private function closeSinkWriter(array $writer): void
    {
        if ($writer['type'] === 'gzip') {
            gzclose($writer['handle']);
            return;
        }

        $writer['handle']->close();
    }

    /**
     * @return array<string, array<int, array{path:string, format:string, compression:?string, batch_max_bytes:?int, batch_max_wait_seconds:?int}>>
     */
    private function buildSourceSinkMap(Config $config): array
    {
        $map = [];

        foreach ($config->sinks as $sink) {
            $path = $sink['path'] ?? null;
            if (!is_string($path) || $path === '') {
                continue;
            }

            $format = $sink['format'] ?? null;
            if (!is_string($format) || $format === '') {
                continue;
            }

            $compression = $sink['compression'] ?? null;
            if ($compression !== null && !is_string($compression)) {
                continue;
            }

            $batchMaxBytes = $sink['batch_max_bytes'] ?? null;
            $batchMaxWaitSeconds = $sink['batch_max_wait_seconds'] ?? null;
            if (($batchMaxBytes === null) !== ($batchMaxWaitSeconds === null)) {
                continue;
            }

            $inputs = $sink['inputs'] ?? [];
            if (!is_array($inputs)) {
                continue;
            }

            foreach ($inputs as $input) {
                if (!is_string($input) || $input === '') {
                    continue;
                }

                $map[$input][] = [
                    'path' => $path,
                    'format' => $format,
                    'compression' => $compression,
                    'batch_max_bytes' => $batchMaxBytes,
                    'batch_max_wait_seconds' => $batchMaxWaitSeconds,
                ];
            }
        }

        foreach ($map as $sourceId => $paths) {
            $unique = [];
            foreach ($paths as $sink) {
                $key =
                    $sink['path']
                    . '|'
                    . $sink['format']
                    . '|'
                    . ($sink['compression'] ?? '')
                    . '|'
                    . ($sink['batch_max_bytes'] ?? '')
                    . '|'
                    . ($sink['batch_max_wait_seconds'] ?? '');
                $unique[$key] = $sink;
            }
            $map[$sourceId] = array_values($unique);
        }

        return $map;
    }

    public function run(Config $config): void
    {
        if ($config->sinks === []) {
            throw new RuntimeException('No sinks configured');
        }

        $sourceToSinks = $this->buildSourceSinkMap($config);
        if ($sourceToSinks === []) {
            throw new RuntimeException('No sources connected to sinks');
        }

        $fileSources = [];

        foreach ($config->sources as $id => $source) {
            if (!array_key_exists($id, $sourceToSinks)) {
                continue;
            }

            if (($source['type'] ?? null) !== 'file') {
                continue;
            }

            $fileSources[$id] = $source;
        }

        if ($fileSources === []) {
            throw new RuntimeException('No file sources configured');
        }

        $fd = inotify_init();

        if ($fd === false) {
            throw new RuntimeException('Init inotify failed');
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
}
