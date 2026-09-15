<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class CustomProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return [];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        ($scenario['run'])($context);
    }
}
