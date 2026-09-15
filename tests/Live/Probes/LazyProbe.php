<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class LazyProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['paginate.lazy'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $chunk = (int) $scenario['chunk'];
        $take = (int) $scenario['take'];

        $models = $this->builder($scenario)->lazy($chunk)->take($take)->values()->all();
        $keys = array_map(fn (Model $model) => (string) $model->getKey(), $models);

        expect(count($models))->toBe((int) ($scenario['expect_count'] ?? $take))
            ->and(count(array_unique($keys)))->toBe(count($keys));

        if (! isset($scenario['expect_count'])) {
            expect($context->requests())->toBe((int) ceil($take / $chunk));
        }
    }
}
