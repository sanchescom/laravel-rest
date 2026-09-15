<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class GetManyProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['read.get-many'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $models = $this->builder($scenario)->getMany($scenario['ids']);

        expect($models->map(fn (Model $model) => (string) $model->getKey())->all())
            ->toBe(array_map('strval', $scenario['ids']))
            ->and($context->requests())->toBe(count($scenario['ids']));
    }
}
