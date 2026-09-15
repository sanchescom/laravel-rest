<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class SimplePaginateProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['paginate.simple'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $perPage = (int) ($scenario['per_page'] ?? 10);
        $first = $this->builder($scenario)->simplePaginate($perPage, 'page', 1);

        expect($first->count())->toBeGreaterThan(0)
            ->and($first->hasMorePages())->toBeTrue();

        if (isset($scenario['last_page'])) {
            $last = $this->builder($scenario)->simplePaginate($perPage, 'page', (int) $scenario['last_page']);

            expect($last->hasMorePages())->toBeFalse();
        }
    }
}
