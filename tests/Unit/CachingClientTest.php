<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Cache\CacheKeys;
use Sanchescom\Rest\Cache\CachingClient;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Tests\Support\ArrayCache;

function cachingClient(ClientInterface $inner, ArrayCache $cache, int $ttl = 60): CachingClient
{
    return new CachingClient($inner, $cache, 'App\\Post', 'main', $ttl);
}

it('caches get responses and replays them without hitting the inner client', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->with('posts', ['page' => 1])->once()
        ->andReturn(new Response(200, [], '{"id":1}'));
    $cache = new ArrayCache;

    $client = cachingClient($inner, $cache);

    expect((string) $client->get('posts', ['page' => 1])->getBody())->toBe('{"id":1}')
        ->and((string) $client->get('posts', ['page' => 1])->getBody())->toBe('{"id":1}');
});

it('uses distinct keys per query, uri, model, client and version', function () {
    $cache = new ArrayCache;
    $version = 0;

    $keys = [
        CacheKeys::entry('main', 'App\\Post', $version, 'posts', ['page' => 1]),
        CacheKeys::entry('main', 'App\\Post', $version, 'posts', ['page' => 2]),
        CacheKeys::entry('main', 'App\\Post', $version, 'others', ['page' => 1]),
        CacheKeys::entry('main', 'App\\User', $version, 'posts', ['page' => 1]),
        CacheKeys::entry('other', 'App\\Post', $version, 'posts', ['page' => 1]),
        CacheKeys::entry('main', 'App\\Post', 1, 'posts', ['page' => 1]),
    ];

    expect(array_unique($keys))->toHaveCount(6);
});

it('refetches after the model version is bumped', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->twice()->andReturn(new Response(200, [], '{}'));
    $cache = new ArrayCache;
    $client = cachingClient($inner, $cache);

    $client->get('posts');
    $cache->set(CacheKeys::version('App\\Post'), 1);
    $client->get('posts');
});

it('stores entries with the configured ttl', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->once()->andReturn(new Response(200, [], '{}'));
    $cache = new ArrayCache;

    cachingClient($inner, $cache, 123)->get('posts');

    expect(array_values($cache->ttls))->toBe([123]);
});

it('does not cache error responses', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->twice()->andThrow(RequestException::fromStatus('posts', 400));
    $cache = new ArrayCache;
    $client = cachingClient($inner, $cache);

    expect(fn () => $client->get('posts'))->toThrow(RequestException::class)
        ->and(fn () => $client->get('posts'))->toThrow(RequestException::class)
        ->and($cache->items)->toBe([]);
});

it('composes getMany from partial cache hits in input order', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->with('posts/1', [])->once()
        ->andReturn(new Response(200, [], '{"id":1}'));
    $inner->shouldReceive('getMany')->with([1 => 'posts/2'])->once()
        ->andReturn([1 => new Response(200, [], '{"id":2}')]);
    $cache = new ArrayCache;
    $client = cachingClient($inner, $cache);

    $client->get('posts/1'); // warm posts/1 — note: keyed with its get() query []

    $responses = $client->getMany(['posts/1', 'posts/2']);

    expect(array_keys($responses))->toBe([0, 1])
        ->and((string) $responses[0]->getBody())->toBe('{"id":1}')
        ->and((string) $responses[1]->getBody())->toBe('{"id":2}');
});

it('passes writes through untouched', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('post')->with('posts', ['a' => 1])->once()->andReturn(new Response(201, [], '{}'));
    $inner->shouldReceive('put')->with('posts/1', ['a' => 2])->once()->andReturn(new Response(200, [], '{}'));
    $inner->shouldReceive('delete')->with('posts/1')->once()->andReturn(new Response(204, [], ''));
    $cache = new ArrayCache;
    $client = cachingClient($inner, $cache);

    $client->post('posts', ['a' => 1]);
    $client->put('posts/1', ['a' => 2]);
    $client->delete('posts/1');

    expect($cache->items)->toBe([]);
});
