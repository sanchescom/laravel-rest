<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class WhereInProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['query.where-in'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $attribute = $scenario['attribute'] ?? $scenario['field'];
        $values = array_map('strval', $scenario['values']);

        $seen = $this->builder($scenario)
            ->whereIn($scenario['field'], $scenario['values'])
            ->get()
            ->map(fn (Model $model) => (string) self::attribute($model, $attribute))
            ->unique()
            ->values()
            ->all();

        expect(array_values(array_diff($seen, $values)))->toBe([])
            ->and(count($seen))->toBeGreaterThanOrEqual(min(2, count($values)));
    }
}
