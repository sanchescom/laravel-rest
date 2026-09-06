<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class FeatureCachedPost extends Model
{
    protected ?string $dataKey = null;

    protected ?int $cacheTtl = 60;
}

afterEach(function () {
    Rest::restore();
});

it('wires the cache store from config', function () {
    expect(Model::getCacheStore())->not->toBeNull();
});

it('caches and flushes end to end on the array store', function () {
    Rest::fake(['feature_cached_posts' => Rest::response([['id' => 1]])]);

    FeatureCachedPost::get();
    FeatureCachedPost::get();
    Rest::assertSentCount(1);

    FeatureCachedPost::flushCache();
    FeatureCachedPost::get();
    Rest::assertSentCount(2);
});
