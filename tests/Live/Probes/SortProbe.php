<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class SortProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['query.sort'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $direction = $scenario['direction'] ?? 'asc';
        $attribute = $scenario['attribute'] ?? $scenario['field'];

        $values = $this->builder($scenario)
            ->orderBy($scenario['field'], $direction)
            ->get()
            ->map(fn (Model $model) => self::attribute($model, $attribute))
            ->values()
            ->all();

        $sorted = $values;
        usort($sorted, fn (mixed $a, mixed $b) => $direction === 'desc' ? $b <=> $a : $a <=> $b);

        expect(count($values))->toBeGreaterThanOrEqual(2)
            ->and($values)->toBe($sorted);
    }
}
