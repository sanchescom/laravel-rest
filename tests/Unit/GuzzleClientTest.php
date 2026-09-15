<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Exceptions\ValidationException;

function makeClient(array $responses, ?array &$history = null): GuzzleClient
{
    $stack = HandlerStack::create(new MockHandler($responses));

    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }

    return new GuzzleClient(new Client([
        'handler' => $stack,
        'base_uri' => 'https://api.test/',
        'http_errors' => false,
    ]));
}

it('performs get and returns psr-7 response', function () {
    $history = [];
    $client = makeClient([new Response(200, [], '{"id":1}')], $history);

    $response = $client->get('users/1');

    expect((string) $response->getBody())->toBe('{"id":1}')
        ->and((string) $history[0]['request']->getUri())->toBe('https://api.test/users/1');
});

it('sends query parameters', function () {
    $history = [];
    makeClient([new Response(200, [], '[]')], $history)->get('users', ['page' => 2]);

    expect((string) $history[0]['request']->getUri())->toBe('https://api.test/users?page=2');
});

it('posts json body', function () {
    $history = [];
    makeClient([new Response(201, [], '{}')], $history)->post('users', ['name' => 'Tim']);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getBody())->toBe('{"name":"Tim"}')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');
});

it('puts json body', function () {
    $history = [];
    makeClient([new Response(200, [], '{}')], $history)->put('users/1', ['name' => 'Tim']);

    expect($history[0]['request']->getMethod())->toBe('PUT');
});

it('sends delete', function () {
    $history = [];
    makeClient([new Response(204, [], '')], $history)->delete('users/1');

    expect($history[0]['request']->getMethod())->toBe('DELETE');
});

it('throws mapped exceptions on error statuses', function () {
    expect(fn () => makeClient([new Response(404, [], '{}')])->get('users/9'))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => makeClient([new Response(422, [], '{"errors":{"a":["b"]}}')])->post('users'))
        ->toThrow(ValidationException::class);
    expect(fn () => makeClient([new Response(500, [], 'oops')])->get('users'))
        ->toThrow(ServerException::class);
});

it('tolerates non-json error bodies', function () {
    try {
        makeClient([new Response(500, [], '<html>')])->get('users');
        $this->fail('Expected exception');
    } catch (ServerException $e) {
        expect($e->body)->toBe([]);
    }
});

it('getMany preserves input order and keys', function () {
    $client = makeClient([
        new Response(200, [], '{"id":"first"}'),
        new Response(200, [], '{"id":"second"}'),
    ]);

    $responses = $client->getMany(['users/1', 'users/2']);

    expect(array_keys($responses))->toBe([0, 1])
        ->and((string) $responses[0]->getBody())->toBe('{"id":"first"}')
        ->and((string) $responses[1]->getBody())->toBe('{"id":"second"}');
});

it('getMany throws when any response is an error', function () {
    makeClient([new Response(200, [], '{}'), new Response(404, [], '{}')])
        ->getMany(['users/1', 'users/2']);
})->throws(ModelNotFoundException::class);

it('getMany rethrows transport failures like get does', function () {
    makeClient([
        new Response(200, [], '{}'),
        new ConnectException('Connection refused', new Request('GET', 'users/2')),
    ])->getMany(['users/1', 'users/2']);
})->throws(ConnectException::class, 'Connection refused');

it('builds itself from config', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['headers' => ['X-Token' => 'abc']],
    ]);

    expect($client)->toBeInstanceOf(GuzzleClient::class);
});
