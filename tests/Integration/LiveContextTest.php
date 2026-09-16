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

it('applies per-request options such as dynamic headers through the resolver', function () {
    $context = new LiveContext('fixture', [
        'name' => 'Fixture',
        'base_uri' => FixtureServer::$baseUri,
        'throttle_ms' => 0,
        'scenarios' => [],
    ]);

    Model::getClientResolver()->client(null, ['headers' => ['X-Live-Check' => 'yes']])->get('echo');

    expect($context->history[0]['request']->getHeaderLine('X-Live-Check'))->toBe('yes')
        ->and($context->history[0]['request']->getHeaderLine('User-Agent'))->toBe(LiveContext::USER_AGENT);
})->group('integration');

it('throttles concurrent requests per host without blocking the pool', function () {
    LiveContext::resetThrottle();

    $context = new LiveContext('fixture-throttle', [
        'name' => 'Fixture',
        'base_uri' => FixtureServer::$baseUri,
        'throttle_ms' => 300,
        'scenarios' => [],
    ]);

    Model::getClientResolver()->client()->getMany(['echo?n=1', 'echo?n=2', 'echo?n=3']);

    expect($context->history)->toHaveCount(3);

    $delays = array_map(fn (array $entry) => (int) ($entry['options']['delay'] ?? 0), $context->history);
    sort($delays);

    expect($delays[0])->toBeGreaterThanOrEqual(0)
        ->and($delays[1])->toBeGreaterThanOrEqual(250)
        ->and($delays[2])->toBeGreaterThanOrEqual(550);
})->group('integration');

it('counts logical requests, not redirect hops', function () {
    $context = new LiveContext('fixture', [
        'name' => 'Fixture',
        'base_uri' => FixtureServer::$baseUri,
        'throttle_ms' => 0,
        'scenarios' => [],
    ]);

    Model::getClientResolver()->client()->get('redirect/1');

    expect($context->history)->toHaveCount(2)
        ->and($context->requests())->toBe(1)
        ->and($context->redirects())->toBe(1);
})->group('integration');
