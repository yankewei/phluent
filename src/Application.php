<?php

declare(strict_types=1);

use Amp\File;
use Revolt\EventLoop;

use function Amp\async;

final class Application
{
    /**
     * @param string[] $sinkPaths
     */
    private function enqueueRead(string $filePath, ?int $maxBytes, array $sinkPaths): void
    {
        async(function () use ($filePath, $maxBytes, $sinkPaths): void {
            if (!is_file($filePath)) {
                return;
            }

            $uniqueSinkPaths = array_values(array_unique($sinkPaths));
            if ($uniqueSinkPaths === []) {
                return;
            }

            try {
                $input = File\openFile($filePath, 'r');
            } catch (Throwable) {
                return;
            }

            $outputs = [];
            foreach ($uniqueSinkPaths as $sinkPath) {
                try {
                    $this->ensureSinkDirectory($sinkPath);
                    $outputs[] = File\openFile($sinkPath, 'a');
                } catch (Throwable) {
                    $input->close();
                    foreach ($outputs as $output) {
                        $output->close();
                    }
                    return;
                }
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

            $input->close();
            foreach ($outputs as $output) {
                $output->close();
            }
        })->await();
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
     * @param array<int, \Amp\File\File> $outputs
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
            $output->write($line);
        }
    }

    /**
     * @return array<string, string[]>
     */
    private function buildSourceSinkMap(Config $config): array
    {
        $map = [];

        foreach ($config->sinks as $sink) {
            $path = $sink['path'] ?? null;
            if (!is_string($path) || $path === '') {
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

                $map[$input][] = $path;
            }
        }

        foreach ($map as $sourceId => $paths) {
            $map[$sourceId] = array_values(array_unique($paths));
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
