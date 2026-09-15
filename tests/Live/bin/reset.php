<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveResults;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$path = LiveResults::path();

if (is_file($path)) {
    unlink($path);
}
