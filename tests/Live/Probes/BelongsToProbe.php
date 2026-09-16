<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class BelongsToProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['relation.belongs-to'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $child = $this->builder($scenario)->get($scenario['id']);
        $related = $child->{$scenario['relation']};

        expect($related)->toBeInstanceOf(Model::class)
            ->and((string) $related->getAttribute($scenario['owner_key'] ?? $related->getKeyName()))
            ->toBe((string) $child->getAttribute($scenario['foreign_key']));
    }
}
