<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\Outage;

abstract class AbstractProbe implements Probe
{
    /**
     * @param  array<string, mixed>  $scenario
     */
    protected function builder(array $scenario): Builder
    {
        $class = $scenario['model'] ?? throw new InvalidArgumentException('Scenario needs a [model] class.');

        /** @var Model $model */
        $model = new $class;
        $builder = $model->newBuilder();

        if (($scenario['query'] ?? null) instanceof Closure) {
            $result = $scenario['query']($builder);
            $builder = $result instanceof Builder ? $result : $builder;
        }

        return $builder;
    }

    /**
     * Run a call that must fail with the given HTTP status; unrelated outages bubble up as skips.
     */
    protected function expectStatus(callable $call, int $status): RequestException
    {
        try {
            $call();
        } catch (RequestException $error) {
            if ($error->status !== $status && Outage::is($error)) {
                throw $error;
            }

            expect($error->status)->toBe($status);

            return $error;
        }

        throw new RuntimeException("Expected HTTP {$status}, but the request succeeded.");
    }

    protected static function attribute(Model $model, string $path): mixed
    {
        return data_get($model->toArray(), $path);
    }
}
