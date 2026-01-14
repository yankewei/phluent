<?php

declare(strict_types=1);

use App\Application;
use App\Config;

require __DIR__ . '/vendor/autoload.php';

$config = Config::load($configFile);

$app = new Application($config);
$app->run();
