<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class FilterProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['query.filter'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $attribute = $scenario['attribute'] ?? $scenario['field'];
        $models = $this->builder($scenario)->where($scenario['field'], $scenario['value'])->get();

        expect($models->count())->toBeGreaterThanOrEqual((int) ($scenario['min'] ?? 1));

        foreach ($models as $model) {
            expect((string) self::attribute($model, $attribute))->toBe((string) $scenario['value']);
        }
    }
}
