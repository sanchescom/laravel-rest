<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;
use Sanchescom\Rest\Tests\Live\Support\LiveUnsupported;

final class UnsupportedProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return [];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        throw new LiveUnsupported((string) ($scenario['reason'] ?? 'Not supported by the package.'));
    }
}
