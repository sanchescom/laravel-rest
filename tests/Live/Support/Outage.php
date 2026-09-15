<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use GuzzleHttp\Exception\TransferException;
use Sanchescom\Rest\Exceptions\RequestException;
use Throwable;

/**
 * Failures that say "the API is unavailable right now", not "the package is wrong".
 */
final class Outage
{
    /** Outage-grade 5xx: gateway/proxy failures, not application server bugs. */
    private const OUTAGE_STATUSES = [502, 503, 504];

    public static function is(Throwable $error): bool
    {
        // The package disables Guzzle's http_errors, so any TransferException
        // (connect failure, timeout, reset, ...) is a transport-level outage.
        if ($error instanceof TransferException) {
            return true;
        }

        if (! $error instanceof RequestException) {
            return false;
        }

        return $error->status === 429
            || in_array($error->status, self::OUTAGE_STATUSES, true)
            || ($error->status >= 520 && $error->status <= 530);
    }
}
