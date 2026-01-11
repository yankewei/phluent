<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$config = Config::load($configFile);

$app = new Application();
$app->run($config);
