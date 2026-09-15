<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class WriteProbe extends AbstractProbe
{
    private const METHODS = ['create' => 'POST', 'update' => 'PUT', 'patch' => 'PATCH', 'delete' => 'DELETE'];

    public function features(array $scenario): array
    {
        $enveloped = isset($scenario['model']) && (new $scenario['model'])->getRequestDataKey() !== null;

        return array_values(array_filter(['write.'.$scenario['op'], $enveloped ? 'write.envelope' : null]));
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $builder = $this->builder($scenario);
        $data = $scenario['data'] ?? [];

        $result = match ($scenario['op']) {
            'create' => $builder->post($data),
            'update', 'patch' => $builder->put($scenario['id'], $data),
            'delete' => $builder->delete($scenario['id']),
        };

        $last = $context->history[array_key_last($context->history)]['request'];

        expect($last->getMethod())->toBe(self::METHODS[$scenario['op']]);

        if ($scenario['op'] === 'delete') {
            expect($result)->toBeTrue();

            return;
        }

        expect($result)->toBeInstanceOf(Model::class);

        foreach ($scenario['expect'] ?? $data as $path => $value) {
            expect(self::attribute($result, $path))->toBe($value);
        }
    }
}
