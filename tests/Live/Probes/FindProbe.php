<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class FindProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['read.find'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $model = $this->builder($scenario)->get($scenario['id']);

        expect($model)->toBeInstanceOf(Model::class)
            ->and((string) $model->getKey())->toBe((string) $scenario['id']);

        foreach ($scenario['fields'] ?? [] as $field) {
            expect(self::attribute($model, $field))->not->toBeNull();
        }
    }
}
