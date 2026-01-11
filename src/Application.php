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

                if (!is_string($path) || $path === '' || !is_string($format) || $format === '') {
                    continue;
                }

                if ($compression !== null && !is_string($compression)) {
                    continue;
                }

                $key = $path . '|' . $format . '|' . ($compression ?? '');
                $uniqueSinks[$key] = [
                    'path' => $path,
                    'format' => $format,
                    'compression' => $compression,
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
                    $outputs[] = [
                        'writer' => $this->openSinkWriter($sinkPath, $sink['compression']),
                        'format' => $sink['format'],
                    ];
                } catch (Throwable) {
                    $input->close();
                    foreach ($outputs as $output) {
                        $this->closeSinkWriter($output['writer']);
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
                $this->closeSinkWriter($output['writer']);
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
     * @param array<int, array{writer:array{type:string, handle:mixed}, format:string}> $outputs
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
            $this->writeToSinkWriter($output['writer'], $formatted);
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
     * @return array<string, array<int, array{path:string, format:string, compression: ?string}>>
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
                ];
            }
        }

        foreach ($map as $sourceId => $paths) {
            $unique = [];
            foreach ($paths as $sink) {
                $key = $sink['path'] . '|' . $sink['format'] . '|' . ($sink['compression'] ?? '');
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
