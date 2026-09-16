<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class ListProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        $dataKey = (new $scenario['model'])->getDataKey();

        return array_values(array_filter([
            'read.list',
            $dataKey !== null ? 'read.data-key' : null,
            $dataKey !== null && str_contains($dataKey, '.') ? 'read.data-key.nested' : null,
        ]));
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $models = $this->builder($scenario)->get();

        expect($models)->toBeInstanceOf(Collection::class)
            ->and($models->count())->toBeGreaterThanOrEqual((int) ($scenario['min'] ?? 1));

        foreach ($scenario['fields'] ?? [] as $field) {
            expect(self::attribute($models->first(), $field))->not->toBeNull();
        }
    }
}
