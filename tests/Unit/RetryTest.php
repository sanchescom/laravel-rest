<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ServerException;

it('retries retriable statuses until success', function () {
    $mock = new MockHandler([
        new Response(503),
        new Response(503),
        new Response(200, [], '{"ok":true}'),
    ]);

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => HandlerStack::create($mock)],
        'retry' => ['times' => 3, 'delay' => 0],
    ]);

    expect($client->get('x')->getStatusCode())->toBe(200)
        ->and($mock->count())->toBe(0);
});

it('gives up after the configured attempts', function () {
    $mock = new MockHandler([
        new Response(503),
        new Response(503),
        new Response(503),
    ]);

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => HandlerStack::create($mock)],
        'retry' => ['times' => 2, 'delay' => 0],
    ]);

    $client->get('x');
})->throws(ServerException::class);

it('does not retry non-retriable statuses', function () {
    $mock = new MockHandler([
        new Response(404),
        new Response(200),
    ]);

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => HandlerStack::create($mock)],
        'retry' => ['times' => 3, 'delay' => 0],
    ]);

    try {
        $client->get('x');
    } catch (Throwable) {
    }

    expect($mock->count())->toBe(1);
});
