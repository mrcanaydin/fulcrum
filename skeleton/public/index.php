<?php

declare(strict_types=1);

use Fulcrum\Foundation\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Application(dirname(__DIR__)))->boot()->run();
