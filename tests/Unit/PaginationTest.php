<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;

class PagedPost extends Model
{
    protected ?string $endpoint = 'paged_posts';

    protected ?string $dataKey = 'data';
}

class DjangoPagedPost extends Model
{
    protected ?string $endpoint = 'paged_posts';

    protected ?string $dataKey = 'results';

    protected array|string|null $pagination = 'django';
}

/**
 * @param  array<string, mixed>  $expectedQuery
 * @param  array<string, mixed>  $body
 * @param  array<string, mixed>|string|null  $pagination
 * @param  array<string, mixed>|string|null  $query
 */
function pagedClient(array $expectedQuery, array $body, array|string|null $pagination = 'laravel', array|string|null $query = null): void
{
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('paged_posts', $expectedQuery)
        ->once()
        ->andReturn(new Response(200, [], json_encode($body)));

    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');

    if ($pagination !== null) {
        $resolver->setPaginationConfig('main', $pagination);
    }

    if ($query !== null) {
        $resolver->setQueryConfig('main', $query);
    }

    Model::setClientResolver($resolver);
}

/**
 * Resolver whose client must never be called.
 *
 * @param  array<string, mixed>|string|null  $pagination
 */
function unusedPagedClient(array|string|null $pagination): void
{
    $resolver = new ClientResolver(['main' => Mockery::mock(ClientInterface::class)]);
    $resolver->setDefaultClient('main');

    if ($pagination !== null) {
        $resolver->setPaginationConfig('main', $pagination);
    }

    Model::setClientResolver($resolver);
}

afterEach(function () {
    Paginator::currentPageResolver(fn () => 1);
    Paginator::currentPathResolver(fn () => '/');
    Model::unsetClientResolver();
});

it('builds a length-aware paginator from response metadata', function () {
    pagedClient(['limit' => 2, 'page' => 2], ['data' => [['id' => 3], ['id' => 4]], 'meta' => ['total' => 5]]);

    $paginator = PagedPost::paginate(2, 'page', 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(5)
        ->and($paginator->lastPage())->toBe(3)
        ->and($paginator->currentPage())->toBe(2)
        ->and($paginator->perPage())->toBe(2)
        ->and($paginator->items()[0])->toBeInstanceOf(PagedPost::class)
        ->and($paginator->items()[1]->id)->toBe(4);
});

it('sends offset and limit for the offset style', function () {
    pagedClient(
        ['limit' => 10, 'offset' => 20],
        ['data' => [], 'meta' => ['total' => 25]],
        ['preset' => 'laravel', 'style' => 'offset'],
    );

    expect(PagedPost::paginate(10, 'page', 3)->currentPage())->toBe(3);
});

it('renders page params through the client query conventions', function () {
    pagedClient(
        ['page' => ['size' => 25, 'number' => 2]],
        ['data' => [], 'meta' => ['total' => 0]],
        ['total' => 'meta.total'],
        'jsonapi',
    );

    expect(PagedPost::paginate(25, 'page', 2)->total())->toBe(0);
});

it('overrides earlier paging and keeps filters', function () {
    pagedClient(['status' => 'active', 'limit' => 5, 'page' => 1], ['data' => [], 'meta' => ['total' => 0]]);

    PagedPost::where('status', 'active')->offset(40)->page(9)->limit(100)->paginate(5, 'page', 1);
});

it('accepts a numeric string total', function () {
    pagedClient(['limit' => 15, 'page' => 1], ['data' => [], 'meta' => ['total' => '7']]);

    expect(PagedPost::paginate(15, 'page', 1)->total())->toBe(7);
});

it('uses the model pagination property over the client config', function () {
    pagedClient(['limit' => 15, 'page' => 1], ['results' => [['id' => 1]], 'count' => 1], 'laravel');

    $paginator = DjangoPagedPost::paginate(15, 'page', 1);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0])->toBeInstanceOf(DjangoPagedPost::class);
});

it('resolves the current page from the request', function () {
    Paginator::currentPageResolver(fn (string $name) => $name === 'p' ? 4 : 1);
    pagedClient(['limit' => 15, 'page' => 4], ['data' => [], 'meta' => ['total' => 100]]);

    $paginator = PagedPost::paginate(15, 'p');

    expect($paginator->currentPage())->toBe(4)
        ->and($paginator->getPageName())->toBe('p');
});

it('falls back to page one for an invalid page in the request', function () {
    Paginator::currentPageResolver(fn () => 0);
    pagedClient(['limit' => 15, 'page' => 1], ['data' => [], 'meta' => ['total' => 0]]);

    expect(PagedPost::paginate(15)->currentPage())->toBe(1);
});

it('requires a configured total key', function (array|string|null $pagination) {
    unusedPagedClient($pagination);

    PagedPost::paginate();
})->with([
    'no config' => [null],
    'preset without total' => ['jsonapi'],
])->throws(
    RestException::class,
    "Pagination total key is not configured for [PagedPost]; set 'pagination.total' or use simplePaginate().",
);

it('fails when the total is missing from the response', function () {
    pagedClient(['limit' => 15, 'page' => 1], ['data' => []]);

    PagedPost::paginate(15, 'page', 1);
})->throws(RestException::class, 'Pagination total key [meta.total] is missing or not an integer in the response for [PagedPost].');

it('fails when the total is not an integer', function () {
    pagedClient(['limit' => 15, 'page' => 1], ['data' => [], 'meta' => ['total' => 'many']]);

    PagedPost::paginate(15, 'page', 1);
})->throws(RestException::class, 'Pagination total key [meta.total] is missing or not an integer in the response for [PagedPost].');

it('rejects a per page below one', function () {
    unusedPagedClient('laravel');

    PagedPost::paginate(0);
})->throws(InvalidArgumentException::class, 'Per page must be at least 1, [0] given.');

it('rejects an explicit page below one', function () {
    unusedPagedClient('laravel');

    PagedPost::paginate(15, 'page', 0);
})->throws(InvalidArgumentException::class, 'Page must be at least 1, [0] given.');

it('builds a simple paginator from a next link', function (?string $next, bool $hasMore) {
    pagedClient(['limit' => 2, 'page' => 1], ['data' => [['id' => 1], ['id' => 2]], 'links' => ['next' => $next]]);

    $paginator = PagedPost::simplePaginate(2, 'page', 1);

    expect($paginator)->toBeInstanceOf(Paginator::class)
        ->and($paginator->hasMorePages())->toBe($hasMore)
        ->and($paginator->count())->toBe(2)
        ->and($paginator->items()[0])->toBeInstanceOf(PagedPost::class);
})->with([
    'next link present' => ['https://api.test/paged_posts?page=2', true],
    'next link null' => [null, false],
    'next link empty' => ['', false],
]);

it('builds a simple paginator from a has-more flag', function (mixed $flag, bool $hasMore) {
    pagedClient(['limit' => 2, 'page' => 1], ['data' => [], 'meta' => ['has_more' => $flag]], ['has_more' => 'meta.has_more']);

    expect(PagedPost::simplePaginate(2, 'page', 1)->hasMorePages())->toBe($hasMore);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'truthy but not true' => [1, false],
]);

it('infers more pages from a full page without metadata config', function (int $items, bool $hasMore) {
    pagedClient(
        ['limit' => 3, 'page' => 1],
        ['data' => array_map(fn (int $id) => ['id' => $id], range(1, $items))],
        null,
    );

    expect(PagedPost::simplePaginate(3, 'page', 1)->hasMorePages())->toBe($hasMore);
})->with([
    'full page' => [3, true],
    'partial page' => [2, false],
]);

it('uses the offset style for simple pagination', function () {
    pagedClient(['limit' => 5, 'offset' => 5], ['data' => []], ['style' => 'offset']);

    expect(PagedPost::simplePaginate(5, 'page', 2)->currentPage())->toBe(2);
});

it('prefers the next link over the has-more flag', function () {
    pagedClient(
        ['limit' => 2, 'page' => 1],
        ['data' => [], 'links' => ['next' => null], 'meta' => ['has_more' => true]],
        ['next' => 'links.next', 'has_more' => 'meta.has_more'],
    );

    expect(PagedPost::simplePaginate(2, 'page', 1)->hasMorePages())->toBeFalse();
});

it('clears a preset next path with null', function () {
    pagedClient(
        ['limit' => 2, 'page' => 1],
        ['data' => [], 'links' => ['next' => 'https://api.test/paged_posts?page=2'], 'meta' => ['has_more' => false]],
        ['preset' => 'laravel', 'next' => null, 'has_more' => 'meta.has_more'],
    );

    expect(PagedPost::simplePaginate(2, 'page', 1)->hasMorePages())->toBeFalse();
});

it('reports more pages when the server returns more items than requested', function () {
    pagedClient(
        ['limit' => 3, 'page' => 1],
        ['data' => array_map(fn (int $id) => ['id' => $id], range(1, 5))],
        null,
    );

    $paginator = PagedPost::simplePaginate(3, 'page', 1);

    expect($paginator->hasMorePages())->toBeTrue()
        ->and($paginator->count())->toBe(3);
});
