<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class EagerProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['eager.'.($scenario['mode'] ?? 'concurrent')];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $parents = $this->builder($scenario)->get();

        expect($parents->count())->toBeGreaterThanOrEqual(2);

        $before = $context->requests();
        $parents->load($scenario['relation']);
        $loading = $context->requests() - $before;

        $loaded = 0;

        foreach ($parents as $parent) {
            $value = $parent->{$scenario['relation']};
            $loaded += $value instanceof Collection ? $value->count() : (int) ($value !== null);
        }

        expect($context->requests() - $before)->toBe($loading)
            ->and($loaded)->toBeGreaterThan(0);

        if (($scenario['mode'] ?? 'concurrent') === 'batch') {
            expect($loading)->toBe(1);
        } else {
            expect($loading)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual($parents->count());
        }
    }
}
