<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Cache\Memo;
use Sanchescom\Rest\Cache\MemoizingClient;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;

afterEach(fn () => Memo::flush());

it('returns the memoized response without calling the inner client', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->with('posts/1', [])->once()->andReturn(new Response(200, [], '{"id":1}'));

    $client = new MemoizingClient($inner, 'App\Post', 'main');

    expect((string) $client->get('posts/1')->getBody())->toBe('{"id":1}')
        ->and((string) $client->get('posts/1')->getBody())->toBe('{"id":1}');
});

it('keys entries by query, headers and client', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->times(4)->andReturn(new Response(200, [], '[]'));

    (new MemoizingClient($inner, 'App\Post', 'main'))->get('posts');
    (new MemoizingClient($inner, 'App\Post', 'main'))->get('posts', ['page' => 2]);
    (new MemoizingClient($inner, 'App\Post', 'main', 'headers-hash'))->get('posts');
    (new MemoizingClient($inner, 'App\Post', 'other'))->get('posts');

    (new MemoizingClient($inner, 'App\Post', 'main'))->get('posts');
    (new MemoizingClient($inner, 'App\Post', 'main'))->get('posts', ['page' => 2]);
    (new MemoizingClient($inner, 'App\Post', 'main', 'headers-hash'))->get('posts');
    (new MemoizingClient($inner, 'App\Post', 'other'))->get('posts');
});

it('does not memoize error responses', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->twice()->andReturn(new Response(500, [], '{}'));

    $client = new MemoizingClient($inner, 'App\Post', 'main');

    $client->get('posts/1');
    $client->get('posts/1');
});

it('fills getMany from memo in input order', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('get')->with('posts/2', [])->once()->andReturn(new Response(200, [], '{"id":2}'));
    $inner->shouldReceive('getMany')->with(['a' => 'posts/1'])->once()->andReturn(['a' => new Response(200, [], '{"id":1}')]);

    $client = new MemoizingClient($inner, 'App\Post', 'main');
    $client->get('posts/2');

    $responses = $client->getMany(['a' => 'posts/1', 'b' => 'posts/2']);

    expect(array_keys($responses))->toBe(['a', 'b'])
        ->and((string) $responses['a']->getBody())->toBe('{"id":1}')
        ->and((string) $responses['b']->getBody())->toBe('{"id":2}')
        ->and((string) $client->get('posts/1')->getBody())->toBe('{"id":1}');
});

it('fails loud when the inner client drops a getMany uri', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('getMany')->once()->andReturn([]);

    (new MemoizingClient($inner, 'App\Post', 'main'))->getMany(['posts/1']);
})->throws(RestException::class, 'Client returned no response for [posts/1].');

it('forgets one model without touching another', function () {
    $posts = Mockery::mock(ClientInterface::class);
    $posts->shouldReceive('get')->twice()->andReturn(new Response(200, [], '{}'));
    $users = Mockery::mock(ClientInterface::class);
    $users->shouldReceive('get')->once()->andReturn(new Response(200, [], '{}'));

    $postClient = new MemoizingClient($posts, 'App\Post', 'main');
    $userClient = new MemoizingClient($users, 'App\User', 'main');

    $postClient->get('x');
    $userClient->get('x');

    Memo::forget('App\Post');

    $postClient->get('x');
    $userClient->get('x');
});

it('passes writes through', function () {
    $inner = Mockery::mock(ClientInterface::class);
    $inner->shouldReceive('post')->with('posts', ['a' => 1])->once()->andReturn(new Response(201, [], '{}'));
    $inner->shouldReceive('put')->with('posts/1', ['a' => 2])->once()->andReturn(new Response(200, [], '{}'));
    $inner->shouldReceive('delete')->with('posts/1')->once()->andReturn(new Response(204, [], ''));

    $client = new MemoizingClient($inner, 'App\Post', 'main');

    expect($client->post('posts', ['a' => 1])->getStatusCode())->toBe(201)
        ->and($client->put('posts/1', ['a' => 2])->getStatusCode())->toBe(200)
        ->and($client->delete('posts/1')->getStatusCode())->toBe(204);
});

it('toggles the enabled flag and flushes when disabled', function () {
    expect(Memo::enabled())->toBeFalse();

    Memo::enable();
    Memo::put('App\Post', 'k', 200, '{}');

    expect(Memo::enabled())->toBeTrue()
        ->and(Memo::get('App\Post', 'k'))->toBe(['status' => 200, 'body' => '{}']);

    Memo::enable(false);

    expect(Memo::enabled())->toBeFalse()
        ->and(Memo::get('App\Post', 'k'))->toBeNull();
});
