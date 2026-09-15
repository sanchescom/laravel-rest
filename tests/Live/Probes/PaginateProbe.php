<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class PaginateProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        $style = (new $scenario['model'])->getPaginationConfig()?->style ?? 'page';

        return ['paginate.total', "paginate.style.{$style}"];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $perPage = (int) ($scenario['per_page'] ?? 10);

        $first = $this->builder($scenario)->paginate($perPage, 'page', 1);
        $second = $this->builder($scenario)->paginate($perPage, 'page', 2);

        expect($first->total())->toBeGreaterThan($perPage)
            ->and($first->count())->toBe($perPage)
            ->and($second->count())->toBeGreaterThan(0);

        $keys = fn (array $models) => array_map(fn (Model $model) => (string) $model->getKey(), $models);
        $firstKeys = $keys($first->items());

        if (! in_array('', $firstKeys, true)) {
            expect(array_values(array_intersect($firstKeys, $keys($second->items()))))->toBe([]);
        }
    }
}
