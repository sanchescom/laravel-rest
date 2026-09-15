<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use GuzzleHttp\Exception\ConnectException;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\ServerException;
use Throwable;

/**
 * Failures that say "the API is unavailable right now", not "the package is wrong".
 */
final class Outage
{
    public static function is(Throwable $error): bool
    {
        return $error instanceof ConnectException
            || $error instanceof ServerException
            || ($error instanceof RequestException && $error->status === 429);
    }
}
