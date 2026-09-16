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
            'header' => (function () use ($sent, $auth) {
                foreach (array_keys($auth['headers']) as $name) {
                    expect($sent->getHeaderLine($name))->toBe($auth['headers'][$name]);
                }
            })(),
        };

        if (isset($scenario['unauthenticated_status'])) {
            $api = $context->api;
            unset($api['client']['auth']);

            // Constructing a context swaps the global client resolver (Model::setClientResolver),
            // so this unauthenticated context becomes the one the next builder call uses.
            new LiveContext($context->slug, $api);

            $this->expectStatus(
                fn () => $this->builder($scenario)->get($scenario['id'] ?? null),
                (int) $scenario['unauthenticated_status'],
            );
        }
    }
}
