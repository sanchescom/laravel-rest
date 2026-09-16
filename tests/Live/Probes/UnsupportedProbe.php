<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;
use Sanchescom\Rest\Tests\Live\Support\LiveUnsupported;
use Sanchescom\Rest\Tests\Live\Support\Outage;

final class UnsupportedProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return [];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $reason = (string) ($scenario['reason'] ?? 'Not supported by the package.');

        if (! isset($scenario['attempt'])) {
            throw new LiveUnsupported($reason);
        }

        $attempt = $scenario['attempt'];

        try {
            Probes::for((string) $attempt['probe'])->run($context, $attempt);
        } catch (LiveUnsupported $nested) {
            throw $nested;
        } catch (\Throwable $error) {
            if (Outage::is($error, $context->api['outage_statuses'] ?? [])) {
                throw $error;
            }

            $firstLine = strtok($error->getMessage(), "\n") ?: $error::class;

            throw new LiveUnsupported("{$reason} (reproduced: {$firstLine})");
        }

        throw new \RuntimeException("Limitation no longer reproduces — the attempt passed: {$reason}");
    }
}
