<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Exceptions\ValidationException;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class StatusProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return [match (true) {
            (int) $scenario['status'] === 422 => 'errors.validation',
            (int) $scenario['status'] >= 500 => 'errors.server',
            default => 'errors.client',
        }];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $status = (int) $scenario['status'];
        $error = $this->expectStatus(fn () => $this->builder($scenario)->get($scenario['id'] ?? null), $status);

        expect($error)->toBeInstanceOf(match (true) {
            $status === 422 => ValidationException::class,
            $status >= 500 => ServerException::class,
            default => RequestException::class,
        });

        foreach ($scenario['errors'] ?? [] as $field) {
            expect($error->errors())->toHaveKey($field);
        }
    }
}
