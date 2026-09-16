<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use RuntimeException;

/**
 * Thrown by a scenario to record a known package limitation instead of a failure.
 */
final class LiveUnsupported extends RuntimeException {}
