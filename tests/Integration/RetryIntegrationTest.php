<?php

declare(strict_types=1);

use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Support\Json;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

it('survives a flaky endpoint over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'retry' => ['times' => 3, 'delay' => 10],
    ]);

    $key = uniqid('flaky');
    $payload = Json::decode((string) $client->get('flaky', ['key' => $key])->getBody());

    expect($payload['attempts'])->toBe(3);
})->group('integration');

it('honors retry-after over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'retry' => ['times' => 2, 'delay' => 10],
    ]);

    $key = uniqid('ra');
    $start = microtime(true);
    $status = $client->get('retry-after', ['key' => $key])->getStatusCode();

    expect($status)->toBe(200)
        ->and(microtime(true) - $start)->toBeGreaterThan(0.9);
})->group('integration');
