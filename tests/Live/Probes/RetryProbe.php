<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class RetryProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['retry.status'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $this->expectStatus(fn () => $this->builder($scenario)->get($scenario['id'] ?? null), (int) $scenario['status']);

        expect($context->requests())->toBe((int) $scenario['attempts']);
    }
}
