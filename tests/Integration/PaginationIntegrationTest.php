<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class FixturePagedItem extends Model
{
    protected ?string $endpoint = 'paged/items';

    protected ?string $dataKey = 'data';
}

function fixturePagination(): void
{
    $resolver = new ClientResolver([
        'fixture' => GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]),
    ]);
    $resolver->setDefaultClient('fixture');
    $resolver->setPaginationConfig('fixture', 'laravel');
    Model::setClientResolver($resolver);
}

it('paginates over real http using response metadata', function () {
    fixturePagination();

    $paginator = FixturePagedItem::paginate(2, 'page', 3);

    expect($paginator->total())->toBe(5)
        ->and($paginator->lastPage())->toBe(3)
        ->and(array_map(fn (FixturePagedItem $item) => $item->id, $paginator->items()))->toBe([5]);
})->group('integration');

it('simple paginates over real http using the next link', function () {
    fixturePagination();

    expect(FixturePagedItem::simplePaginate(2, 'page', 2)->hasMorePages())->toBeTrue()
        ->and(FixturePagedItem::simplePaginate(2, 'page', 3)->hasMorePages())->toBeFalse();
})->group('integration');

it('lazily walks every page over real http', function () {
    fixturePagination();

    $ids = FixturePagedItem::lazy(2)->map(fn (FixturePagedItem $item) => $item->id)->values()->all();

    expect($ids)->toBe([1, 2, 3, 4, 5]);
})->group('integration');
