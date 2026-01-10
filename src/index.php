<?php

declare(strict_types=1);

use Amp\File;
use Revolt\EventLoop;

use function Amp\async;

require __DIR__ . '/vendor/autoload.php';

$fd = inotify_init();

if ($fd === false) {
    throw new RuntimeException('Init inotify failed');
}

stream_set_blocking($fd, false);

$watchDir = __DIR__ . '../data';

$watchId = inotify_add_watch($fd, $watchDir, IN_CLOSE_WRITE | IN_MOVED_TO);

EventLoop::onReadable($fd, function ($callbackId, $fd) use ($watchDir): string {
    $events = inotify_read($fd);

    if ($events === false) {
        throw new RuntimeException('Events must not false, should be an array contain multiple event');
    }

    /** @var array{wd:int,mask:int,cookie:int,name:string} $event */
    foreach ($events as $event) {
        $file_path = $watchDir . DIRECTORY_SEPARATOR . $event['name'];
        $contents = async(fn(): string => File\read($file_path));
    }

    return $callbackId;
});

EventLoop::run();
