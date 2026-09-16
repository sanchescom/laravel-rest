<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveResults;

require dirname(__DIR__, 3).'/vendor/autoload.php';

// A batch run (LIVE_ONLY) only ever verifies a slice of the catalog; wiping
// previous results here would drop every other API's results from the report.
// Full runs still reset, and LiveResults::record() lets the last write per
// scenario win, so re-running a batch simply refreshes its own rows.
if (getenv('LIVE_ONLY') === false || getenv('LIVE_ONLY') === '') {
    $path = LiveResults::path();

    if (is_file($path)) {
        unlink($path);
    }
}
