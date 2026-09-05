<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Sanchescom\Rest\Collection;

beforeEach(function () {
    Container::setInstance(new Container);
});

afterEach(function () {
    Container::setInstance(null);
});

it('paginates the collection in memory', function () {
    $paginator = (new Collection(range(1, 45)))->paginate(10, 'page', 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(45)
        ->and($paginator->currentPage())->toBe(2)
        ->and($paginator->items())->toBe(range(11, 20));
});

it('paginates an empty collection', function () {
    $paginator = (new Collection)->paginate(10, 'page', 1);

    expect($paginator->total())->toBe(0)->and($paginator->items())->toBe([]);
});
