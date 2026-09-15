<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\LazyCollection;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;

class LazyPost extends Model
{
    protected ?string $endpoint = 'lazy_posts';

    protected ?string $dataKey = 'data';
}

/**
 * @param  list<array{0: array<string, mixed>, 1: array<string, mixed>}>  $pages  [expected query, response body] in request order
 * @param  array<string, mixed>|string|null  $pagination
 */
function lazyClient(array $pages, array|string|null $pagination = 'laravel'): void
{
    $client = Mockery::mock(ClientInterface::class);

    foreach ($pages as [$query, $body]) {
        $client->shouldReceive('get')
            ->with('lazy_posts', $query)
            ->once()
            ->andReturn(new Response(200, [], json_encode($body)));
    }

    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');

    if ($pagination !== null) {
        $resolver->setPaginationConfig('main', $pagination);
    }

    Model::setClientResolver($resolver);
}

/**
 * @param  list<int>  $ids
 * @return list<array{id: int}>
 */
function lazyItems(array $ids): array
{
    return array_map(fn (int $id) => ['id' => $id], $ids);
}

/**
 * @param  array<int, Model>  $models
 * @return list<mixed>
 */
function lazyKeys(array $models): array
{
    return array_values(array_map(fn (Model $model) => $model->getKey(), $models));
}

afterEach(fn () => Model::unsetClientResolver());

it('yields models from every page until the next link runs out', function () {
    lazyClient([
        [['limit' => 2, 'page' => 1], ['data' => lazyItems([1, 2]), 'links' => ['next' => 'p2']]],
        [['limit' => 2, 'page' => 2], ['data' => lazyItems([3, 4]), 'links' => ['next' => 'p3']]],
        [['limit' => 2, 'page' => 3], ['data' => lazyItems([5]), 'links' => ['next' => null]]],
    ]);

    $lazy = LazyPost::lazy(2);
    $models = $lazy->all();

    expect($lazy)->toBeInstanceOf(LazyCollection::class)
        ->and(lazyKeys($models))->toBe([1, 2, 3, 4, 5])
        ->and($models[0])->toBeInstanceOf(LazyPost::class);
});

it('stops on a partial page without pagination metadata', function () {
    lazyClient([
        [['limit' => 2, 'page' => 1], ['data' => lazyItems([1, 2])]],
        [['limit' => 2, 'page' => 2], ['data' => lazyItems([3])]],
    ], null);

    expect(lazyKeys(LazyPost::lazy(2)->all()))->toBe([1, 2, 3]);
});

it('stops on an empty page', function () {
    lazyClient([
        [['limit' => 2, 'page' => 1], ['data' => lazyItems([1, 2])]],
        [['limit' => 2, 'page' => 2], ['data' => []]],
    ], null);

    expect(lazyKeys(LazyPost::lazy(2)->all()))->toBe([1, 2]);
});

it('walks offsets for the offset style', function () {
    lazyClient([
        [['limit' => 2, 'offset' => 0], ['data' => lazyItems([1, 2])]],
        [['limit' => 2, 'offset' => 2], ['data' => lazyItems([3])]],
    ], ['style' => 'offset']);

    expect(lazyKeys(LazyPost::lazy(2)->all()))->toBe([1, 2, 3]);
});

it('uses a chunk size of 100 by default', function () {
    lazyClient([
        [['limit' => 100, 'page' => 1], ['data' => lazyItems([1])]],
    ], null);

    expect(lazyKeys(LazyPost::lazy()->all()))->toBe([1]);
});

it('keeps filters on every page', function () {
    lazyClient([
        [['status' => 'active', 'limit' => 1, 'page' => 1], ['data' => lazyItems([1])]],
        [['status' => 'active', 'limit' => 1, 'page' => 2], ['data' => []]],
    ], null);

    expect(lazyKeys(LazyPost::where('status', 'active')->lazy(1)->all()))->toBe([1]);
});

it('fails when the api returns the same page twice', function () {
    lazyClient([
        [['limit' => 2, 'page' => 1], ['data' => lazyItems([1, 2])]],
        [['limit' => 2, 'page' => 2], ['data' => lazyItems([1, 2])]],
    ], null);

    LazyPost::lazy(2)->all();
})->throws(RestException::class, "API returned the same page twice for [LazyPost]; check the pagination 'style' and query 'names'.");

it('does not treat keyless pages as repeated', function () {
    lazyClient([
        [['limit' => 1, 'page' => 1], ['data' => [['title' => 'a']]]],
        [['limit' => 1, 'page' => 2], ['data' => [['title' => 'b']]]],
        [['limit' => 1, 'page' => 3], ['data' => []]],
    ], null);

    $titles = array_map(fn (LazyPost $post) => $post->title, LazyPost::lazy(1)->all());

    expect(array_values($titles))->toBe(['a', 'b']);
});

it('sends no request until iterated', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldNotReceive('get');
    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    Model::setClientResolver($resolver);

    expect(LazyPost::lazy())->toBeInstanceOf(LazyCollection::class);
});

it('rejects a chunk size below one immediately', function () {
    Model::setClientResolver(new ClientResolver(['main' => Mockery::mock(ClientInterface::class)]));

    LazyPost::lazy(0);
})->throws(InvalidArgumentException::class, 'Chunk size must be at least 1, [0] given.');
