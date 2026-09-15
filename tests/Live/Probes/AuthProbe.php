<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class AuthProbe extends AbstractProbe
{
    public function features(array $scenario): array
    {
        return ['auth.'.$scenario['client']['auth']['driver']];
    }

    public function run(LiveContext $context, array $scenario): void
    {
        $auth = $scenario['client']['auth'];

        $this->builder($scenario)->get($scenario['id'] ?? null);

        $sent = $context->history[0]['request'];

        match ($auth['driver']) {
            'bearer' => expect($sent->getHeaderLine('Authorization'))->toBe('Bearer '.$auth['token']),
            'basic' => expect($sent->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode($auth['username'].':'.$auth['password'])),
            'header' => array_map(
                fn (string $name) => expect($sent->getHeaderLine($name))->toBe($auth['headers'][$name]),
                array_keys($auth['headers']),
            ),
        };

        if (isset($scenario['unauthenticated_status'])) {
            new LiveContext($context->slug, $context->api);

            $this->expectStatus(
                fn () => $this->builder($scenario)->get($scenario['id'] ?? null),
                (int) $scenario['unauthenticated_status'],
            );
        }
    }
}
