<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

it('serves ping', function () {
    $body = file_get_contents(FixtureServer::$baseUri.'ping');
    expect($body)->toBe('{"pong":true}');
})->group('integration');

it('serves plain and enveloped items', function () {
    expect(json_decode(file_get_contents(FixtureServer::$baseUri.'plain/items'), true))
        ->toBe([['id' => 1], ['id' => 2]])
        ->and(json_decode(file_get_contents(FixtureServer::$baseUri.'enveloped/items'), true))
        ->toBe(['data' => [['id' => 1], ['id' => 2]]]);
})->group('integration');

it('echoes query and headers', function () {
    $ctx = stream_context_create(['http' => ['header' => "X-Probe: yes\r\n"]]);
    $echo = json_decode(file_get_contents(FixtureServer::$baseUri.'echo?a=1&b=2', false, $ctx), true);
    expect($echo['query'])->toBe(['a' => '1', 'b' => '2'])
        ->and($echo['headers']['X-Probe'] ?? null)->toBe('yes');
})->group('integration');
