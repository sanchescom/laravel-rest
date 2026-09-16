<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class NotFoundProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['errors.not-found'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $error = $this->expectStatus(fn () => $this->builder($scenario)->get($scenario['id']), 404);

        expect($error)->toBeInstanceOf(ModelNotFoundException::class);
    }
}
