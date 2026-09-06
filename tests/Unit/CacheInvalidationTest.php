<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Tests\Support\ArrayCache;

class InvalidatedPost extends Model
{
    protected ?string $dataKey = null;

    protected ?int $cacheTtl = 60;
}

beforeEach(function () {
    Model::setCacheStore(new ArrayCache);
});

afterEach(function () {
    Model::setCacheStore(null);
    InvalidatedPost::flushEventListeners();
    Rest::restore();
});

it('invalidates the cache after post', function () {
    Rest::fake(['invalidated_posts*' => Rest::response(['id' => 1])]);

    InvalidatedPost::get();
    InvalidatedPost::post(['title' => 'x']);
    InvalidatedPost::get();

    Rest::assertSentCount(3);
});

it('invalidates the cache after put and delete', function () {
    Rest::fake(['invalidated_posts*' => Rest::response(['id' => 1])]);

    InvalidatedPost::get();
    InvalidatedPost::put(1, ['title' => 'y']);
    InvalidatedPost::get();
    InvalidatedPost::delete(1);
    InvalidatedPost::get();

    Rest::assertSentCount(5);
});

it('does not invalidate when a write is cancelled', function () {
    Rest::fake(['invalidated_posts*' => Rest::response(['id' => 1])]);
    InvalidatedPost::creating(fn () => false);

    InvalidatedPost::get();
    InvalidatedPost::post(['title' => 'x']);
    InvalidatedPost::get();

    Rest::assertSentCount(1);
});
