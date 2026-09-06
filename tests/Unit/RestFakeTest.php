<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class FakedPost extends Model
{
    protected ?string $dataKey = null;
}

afterEach(function () {
    Rest::restore();
});

it('serves faked responses by pattern', function () {
    Rest::fake([
        'faked_posts/*' => Rest::response(['id' => 7, 'title' => 'faked']),
        'faked_posts' => Rest::response([['id' => 1]]),
    ]);

    expect(FakedPost::get(7)->title)->toBe('faked')
        ->and(FakedPost::get())->toHaveCount(1);
});

it('records requests and asserts on them', function () {
    Rest::fake(['faked_posts' => Rest::response([])]);

    FakedPost::post(['title' => 'x']);

    Rest::assertSentCount(1);
    Rest::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->uri() === 'faked_posts'
        && $request->data() === ['title' => 'x']);
    Rest::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('throws on unmatched uris', function () {
    Rest::fake(['other' => Rest::response([])]);

    FakedPost::get();
})->throws(RestException::class, 'No fake response defined');

it('maps error statuses to typed exceptions', function () {
    Rest::fake(['faked_posts/*' => Rest::response(['error' => 'gone'], 404)]);

    FakedPost::get(9);
})->throws(ModelNotFoundException::class);

it('restores the previous resolver', function () {
    $before = Model::getClientResolver();

    Rest::fake(['x' => Rest::response([])]);

    expect(Model::getClientResolver())->not->toBe($before);

    Rest::restore();

    expect(Model::getClientResolver())->toBe($before);
});
