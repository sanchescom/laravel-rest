<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class HeadersProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['headers.dynamic'];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $model = $this->builder($scenario)->withHeaders($scenario['headers'])->get($scenario['id'] ?? null);
        $sent = $context->history[0]['request'];
        $echoed = array_change_key_case($model->toArray(), CASE_LOWER);

        foreach ($scenario['headers'] as $name => $value) {
            expect($sent->getHeaderLine($name))->toBe($value);

            if ($scenario['echo'] ?? true) {
                expect($echoed[strtolower($name)] ?? null)->toBe($value);
            }
        }
    }
}
