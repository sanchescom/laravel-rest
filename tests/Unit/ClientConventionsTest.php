<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ValidationException;

function conventionsClient(array $responses, array $config, ?array &$history = null): GuzzleClient
{
    $stack = HandlerStack::create(new MockHandler($responses));

    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }

    return GuzzleClient::fromConfig(array_merge([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => $stack],
    ], $config));
}

it('sends patch when update_method is patch', function () {
    $history = [];
    conventionsClient([new Response(200, [], '{}')], ['update_method' => 'patch'], $history)
        ->put('posts/1', ['a' => 1]);

    expect($history[0]['request']->getMethod())->toBe('PATCH');
});

it('sends put by default', function () {
    $history = [];
    conventionsClient([new Response(200, [], '{}')], [], $history)->put('posts/1', ['a' => 1]);

    expect($history[0]['request']->getMethod())->toBe('PUT');
});

it('rejects unknown update methods', function () {
    GuzzleClient::fromConfig(['base_uri' => 'https://api.test/', 'update_method' => 'teleport']);
})->throws(InvalidArgumentException::class);

it('reads validation errors from a configured dot-notation key', function () {
    $client = conventionsClient(
        [new Response(422, [], '{"error":{"details":{"email":["Invalid"]}}}')],
        ['errors_key' => 'error.details'],
    );

    try {
        $client->post('posts', []);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['email' => ['Invalid']]);
    }
});

it('keeps the default errors key working', function () {
    $client = conventionsClient([new Response(422, [], '{"errors":{"email":["Invalid"]}}')], []);

    try {
        $client->post('posts', []);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['email' => ['Invalid']]);
    }
});
