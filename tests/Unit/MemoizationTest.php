<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Sanchescom\Rest\Cache\Memo;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Tests\Support\ArrayCache;

class MemoPost extends Model
{
    protected ?string $endpoint = 'memo_posts';

    protected ?string $dataKey = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(MemoUser::class, 'userId');
    }
}

class MemoUser extends Model
{
    protected ?string $endpoint = 'memo_users';

    protected ?string $dataKey = null;
}

class CachedMemoPost extends Model
{
    protected ?string $endpoint = 'memo_posts';

    protected ?string $dataKey = null;

    protected ?int $cacheTtl = 60;
}

afterEach(function () {
    Rest::memoize(false);
    Rest::restore();
    Model::setCacheStore(null);
});

it('is disabled by default', function () {
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);

    MemoPost::get(1);
    MemoPost::get(1);

    Rest::assertSentCount(2);
});

it('sends one request for a repeated get by id and returns fresh instances', function () {
    Rest::memoize();
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);

    $first = MemoPost::get(1);
    $second = MemoPost::get(1);

    Rest::assertSentCount(1);
    expect($second)->toBeInstanceOf(MemoPost::class)
        ->and($second->id)->toBe(1)
        ->and($second)->not->toBe($first);
});

it('memoizes lists per compiled query', function () {
    Rest::memoize();
    Rest::fake(['memo_posts' => Rest::response([['id' => 1]])]);

    MemoPost::where('status', 'active')->get();
    MemoPost::where('status', 'active')->get();
    MemoPost::where('status', 'draft')->get();

    Rest::assertSentCount(2);
});

it('loads a shared belongsTo author once', function () {
    Rest::memoize();
    Rest::fake(['memo_users/*' => Rest::response(['id' => 7, 'name' => 'Ann'])]);

    $posts = [new MemoPost(['userId' => 7]), new MemoPost(['userId' => 7]), new MemoPost(['userId' => 7])];

    $names = array_map(fn (MemoPost $post) => $post->author->name, $posts);

    expect($names)->toBe(['Ann', 'Ann', 'Ann']);
    Rest::assertSentCount(1);
});

it('forgets a model after a successful write', function () {
    Rest::memoize();
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);

    MemoPost::get(1);
    MemoPost::put(1, ['title' => 'changed']);
    MemoPost::get(1);

    Rest::assertSentCount(3);
});

it('keeps other models memoized after a write', function () {
    Rest::memoize();
    Rest::fake([
        'memo_users/*' => Rest::response(['id' => 7]),
        'memo_posts/*' => Rest::response(['id' => 1]),
    ]);

    MemoUser::get(7);
    MemoPost::put(1, ['title' => 'changed']);
    MemoUser::get(7);

    Rest::assertSentCount(2);
});

it('bypasses memo with withoutCache', function () {
    Rest::memoize();
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);

    MemoPost::withoutCache()->get(1);
    MemoPost::withoutCache()->get(1);

    Rest::assertSentCount(2);
});

it('never memoizes lazy iteration', function () {
    Rest::memoize();
    Rest::fake(['memo_posts' => Rest::response([['id' => 1]])]);

    MemoPost::lazy(5)->all();
    MemoPost::lazy(5)->all();

    Rest::assertSentCount(2);
});

it('serves memo hits without touching the persistent cache', function () {
    Rest::memoize();
    Model::setCacheStore(new ArrayCache);
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);

    CachedMemoPost::get(1);

    $store = Mockery::mock(CacheInterface::class);
    $store->shouldNotReceive('get');
    $store->shouldNotReceive('set');
    Model::setCacheStore($store);

    expect(CachedMemoPost::get(1)->id)->toBe(1);
    Rest::assertSentCount(1);
});

it('flushes memo when faking again', function () {
    Rest::memoize();
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);
    MemoPost::get(1);

    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);
    MemoPost::get(1);

    Rest::assertSentCount(1);
});

it('flushes memo on restore', function () {
    Memo::put(MemoPost::class, 'key', 200, '{}');

    Rest::restore();

    expect(Memo::get(MemoPost::class, 'key'))->toBeNull();
});

it('flushes everything with flushMemo', function () {
    Rest::memoize();
    Rest::fake(['memo_posts/*' => Rest::response(['id' => 1])]);

    MemoPost::get(1);
    Rest::flushMemo();
    MemoPost::get(1);

    Rest::assertSentCount(2);
});
