<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveCatalog;
use Sanchescom\Rest\Tests\Live\Support\LiveReport;
use Sanchescom\Rest\Tests\Live\Support\LiveResults;

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$version = trim((string) shell_exec('git -C '.escapeshellarg($root).' describe --tags --always --dirty 2>/dev/null')) ?: 'unknown';

file_put_contents(
    $root.'/docs/live-verification.md',
    LiveReport::render(LiveCatalog::apis(), LiveResults::read(), $version, gmdate('Y-m-d H:i').' UTC'),
);

echo "Wrote docs/live-verification.md\n";
