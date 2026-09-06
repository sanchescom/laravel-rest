<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Tests\Support\ArrayCache;

class CachedPost extends Model
{
    protected ?string $dataKey = null;

    protected ?int $cacheTtl = 60;
}

class UncachedPost extends Model
{
    protected ?string $dataKey = null;
}

beforeEach(function () {
    Model::setCacheStore(new ArrayCache);
});

afterEach(function () {
    Model::setCacheStore(null);
    Rest::restore();
});

it('serves the page-flip scenario from cache', function () {
    Rest::fake(['cached_posts' => Rest::response([['id' => 1]])]);

    CachedPost::page(1)->get();
    CachedPost::page(2)->get();
    CachedPost::page(1)->get();

    Rest::assertSentCount(2);
});

it('does not cache models without cacheTtl', function () {
    Rest::fake(['uncached_posts' => Rest::response([])]);

    UncachedPost::get();
    UncachedPost::get();

    Rest::assertSentCount(2);
});

it('bypasses the cache with withoutCache', function () {
    Rest::fake(['cached_posts' => Rest::response([])]);

    CachedPost::withoutCache()->get();
    CachedPost::withoutCache()->get();

    Rest::assertSentCount(2);
});

it('opts a chain in with withCache', function () {
    Rest::fake(['uncached_posts' => Rest::response([])]);

    UncachedPost::withCache(60)->get();
    UncachedPost::withCache(60)->get();

    Rest::assertSentCount(1);
});

it('uses the default ttl when withCache has no argument', function () {
    Rest::fake(['uncached_posts' => Rest::response([])]);
    Model::setCacheStore($store = new ArrayCache, 777);

    UncachedPost::withCache()->get();

    $ttls = array_values(array_filter($store->ttls));
    expect($ttls)->toBe([777]);
});

it('throws when caching is requested without a store', function () {
    Rest::fake(['cached_posts' => Rest::response([])]);
    Model::setCacheStore(null);

    CachedPost::get();
})->throws(RestException::class, 'no cache store');

it('flushCache invalidates all entries for the model', function () {
    Rest::fake(['cached_posts' => Rest::response([])]);

    CachedPost::get();
    CachedPost::flushCache();
    CachedPost::get();

    Rest::assertSentCount(2);
});
