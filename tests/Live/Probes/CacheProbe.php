<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;
use Sanchescom\Rest\Tests\Support\ArrayCache;

final class CacheProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['cache.'.$scenario['kind']];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $memo = $scenario['kind'] === 'memo';

        if ($memo) {
            Rest::memoize();
        } else {
            Model::setCacheStore(new ArrayCache);
        }

        $read = function () use ($scenario, $memo) {
            $builder = $this->builder($scenario);

            return ($memo ? $builder : $builder->withCache(60))->get($scenario['id'] ?? null);
        };

        $first = $read();
        $second = $read();

        expect($context->requests())->toBe(1)
            ->and($second->toArray())->toBe($first->toArray());
    }
}
