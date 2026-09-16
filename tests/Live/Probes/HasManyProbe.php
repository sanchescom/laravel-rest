<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class HasManyProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return [! empty($scenario['nested']) ? 'relation.nested' : 'relation.has-many'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $parent = $this->builder($scenario)->get($scenario['id']);
        $items = $parent->{$scenario['relation']};

        expect($items->count())->toBeGreaterThanOrEqual(1);

        if (isset($scenario['foreign_key'])) {
            foreach ($items as $item) {
                expect((string) $item->getAttribute($scenario['foreign_key']))->toBe((string) $parent->getKey());
            }
        }

        if (! empty($scenario['nested'])) {
            $last = $context->history[array_key_last($context->history)]['request'];

            expect(str_ends_with($last->getUri()->getPath(), (string) $scenario['path_suffix']))->toBeTrue();
        }
    }
}
