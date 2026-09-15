<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveCatalog;
use Sanchescom\Rest\Tests\Live\Support\LiveRunner;

it('verifies live api behaviour', function (string $slug, string $scenario) {
    LiveRunner::run($slug, $scenario);
})->with(LiveCatalog::scenarios())->group('live', 'catalog');
