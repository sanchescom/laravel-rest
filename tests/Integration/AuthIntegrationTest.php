<?php

declare(strict_types=1);

use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

it('authenticates with bearer over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'auth' => ['driver' => 'bearer', 'token' => 'secret-token'],
    ]);

    expect($client->get('auth/bearer')->getStatusCode())->toBe(200);
})->group('integration');

it('authenticates with basic over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'auth' => ['driver' => 'basic', 'username' => 'user', 'password' => 'pass'],
    ]);

    expect($client->get('auth/basic')->getStatusCode())->toBe(200);
})->group('integration');

it('authenticates with custom headers over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'auth' => ['driver' => 'header', 'headers' => ['X-Api-Key' => 'k123']],
    ]);

    expect($client->get('auth/header')->getStatusCode())->toBe(200);
})->group('integration');

it('gets 401 without credentials', function () {
    $client = GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]);

    $client->get('auth/bearer');
})->group('integration')->throws(RequestException::class);
