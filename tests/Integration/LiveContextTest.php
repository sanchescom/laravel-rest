<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

afterEach(fn () => Model::unsetClientResolver());

it('records every retry attempt with auth headers', function () {
    $context = new LiveContext('fixture', [
        'name' => 'Fixture',
        'base_uri' => FixtureServer::$baseUri,
        'throttle_ms' => 0,
        'client' => [
            'auth' => ['driver' => 'bearer', 'token' => 'live-token'],
            'retry' => ['times' => 3, 'delay' => 1],
        ],
        'scenarios' => [],
    ]);

    Model::getClientResolver()->client()->get('flaky', ['key' => uniqid('live-context')]);

    expect($context->requests())->toBe(3);

    foreach ($context->history as $entry) {
        expect($entry['request']->getHeaderLine('Authorization'))->toBe('Bearer live-token')
            ->and($entry['request']->getHeaderLine('User-Agent'))->toBe(LiveContext::USER_AGENT);
    }
})->group('integration');
