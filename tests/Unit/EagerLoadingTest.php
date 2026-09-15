<?php

declare(strict_types=1);

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\EagerLoader;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Relations\HasOne;
use Sanchescom\Rest\Relations\Relation;
use Sanchescom\Rest\Rest;

class EagerPost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function comments(): HasMany
    {
        return $this->hasMany(EagerComment::class, 'postId');
    }

    public function batchedComments(): HasMany
    {
        return $this->hasMany(EagerComment::class, 'postId')->batch();
    }

    public function nestedComments(): HasMany
    {
        return $this->hasMany(EagerComment::class)->nested();
    }

    public function latestComment(): HasOne
    {
        return $this->hasOne(EagerComment::class, 'postId');
    }

    public function batchedLatestComment(): HasOne
    {
        return $this->hasOne(EagerComment::class, 'postId')->batch();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(EagerUser::class, 'userId');
    }

    public function batchedAuthor(): BelongsTo
    {
        return $this->belongsTo(EagerUser::class, 'userId')->batch();
    }

    public function custom(): EagerCustomRelation
    {
        return new EagerCustomRelation($this, EagerUser::class);
    }
}

class EagerComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(EagerUser::class, 'userId');
    }
}

class EagerUser extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

class EagerCustomRelation extends Relation
{
    public function getResults(): mixed
    {
        return null;
    }
}

/**
 * @param  list<array<string, mixed>>  $posts
 * @return Collection<int, Model>
 */
function eagerPosts(array $posts): Collection
{
    return (new EagerPost)->newCollection(array_map(fn (array $attributes) => new EagerPost($attributes), $posts));
}

afterEach(function () {
    Rest::memoize(false);
    Rest::restore();
    Model::unsetClientResolver();
});

it('loads belongsTo concurrently once per unique key', function () {
    Rest::fake([
        'users/7' => Rest::response(['id' => 7, 'name' => 'Ann']),
        'users/8' => Rest::response(['id' => 8, 'name' => 'Bob']),
    ]);
    $posts = eagerPosts([['id' => 1, 'userId' => 7], ['id' => 2, 'userId' => 8], ['id' => 3, 'userId' => 7]]);

    (new EagerLoader)->load($posts, ['author']);

    expect($posts->map(fn (EagerPost $post) => $post->author->name)->all())->toBe(['Ann', 'Bob', 'Ann']);
    Rest::assertSentCount(2);
});

it('loads belongsTo in one whereIn request when batched', function () {
    Rest::fake(['users' => Rest::response([['id' => 7, 'name' => 'Ann'], ['id' => '8', 'name' => 'Bob']])]);
    $posts = eagerPosts([['id' => 1, 'userId' => 7], ['id' => 2, 'userId' => 8], ['id' => 3, 'userId' => 9]]);

    (new EagerLoader)->load($posts, ['batchedAuthor']);

    expect($posts[0]->batchedAuthor->name)->toBe('Ann')
        ->and($posts[1]->batchedAuthor->name)->toBe('Bob')
        ->and($posts[2]->batchedAuthor)->toBeNull();
    Rest::assertSentCount(1);
    Rest::assertSent(fn ($request) => $request->uri() === 'users' && $request->query() === ['id' => '7,8,9']);
});

it('applies constraints to every concurrent belongsTo request', function () {
    Rest::fake(['users/*' => Rest::response(['id' => 7])]);
    $posts = eagerPosts([['id' => 1, 'userId' => 7]]);

    (new EagerLoader)->load($posts, ['author' => fn (Builder $query) => $query->withQuery(['fields' => 'name'])]);

    Rest::assertSent(fn ($request) => $request->uri() === 'users/7' && $request->query() === ['fields' => 'name']);
});

it('sets null and sends nothing for parents without a foreign key', function () {
    Rest::fake([]);
    $posts = eagerPosts([['id' => 1]]);

    (new EagerLoader)->load($posts, ['author', 'batchedAuthor']);

    expect($posts[0]->author)->toBeNull()
        ->and($posts[0]->batchedAuthor)->toBeNull();
    Rest::assertSentCount(0);
});

it('does not request again when the eager loaded relation is read', function () {
    Rest::fake(['users/7' => Rest::response(['id' => 7])]);
    $posts = eagerPosts([['id' => 1, 'userId' => 7]]);

    (new EagerLoader)->load($posts, ['author']);
    $posts[0]->author;
    $posts[0]->author;

    Rest::assertSentCount(1);
});

it('propagates http errors from eager loading', function () {
    Rest::fake(['users/7' => Rest::response(['error' => 'gone'], 404)]);

    (new EagerLoader)->load(eagerPosts([['id' => 1, 'userId' => 7]]), ['author']);
})->throws(ModelNotFoundException::class);

it('rejects unknown relations', function () {
    Rest::fake([]);

    (new EagerLoader)->load(eagerPosts([['id' => 1]]), ['missing']);
})->throws(InvalidArgumentException::class, 'Relation [missing] is not defined on [EagerPost].');

it('rejects relations without eager loading support', function () {
    Rest::fake([]);

    (new EagerLoader)->load(eagerPosts([['id' => 1]]), ['custom']);
})->throws(RestException::class, 'Relation [custom] on [EagerPost] does not support eager loading.');

it('does nothing for an empty collection', function () {
    Rest::fake([]);

    (new EagerLoader)->load((new EagerPost)->newCollection(), ['author', 'missing']);

    Rest::assertSentCount(0);
});

it('loads nested belongsTo relations level by level', function () {
    Rest::fake([
        'users/7' => Rest::response(['id' => 7, 'name' => 'Ann']),
    ]);
    $comments = (new EagerComment)->newCollection([
        new EagerComment(['id' => 10, 'userId' => 7]),
        new EagerComment(['id' => 11, 'userId' => 7]),
    ]);

    (new EagerLoader)->load($comments, ['author']);

    expect($comments[1]->author->name)->toBe('Ann');
    Rest::assertSentCount(1);
});

it('loads hasMany concurrently with one request per parent', function () {
    Rest::fake(['comments' => Rest::response([['id' => 10, 'postId' => 1]])]);
    $posts = eagerPosts([['id' => 1], ['id' => 2]]);

    (new EagerLoader)->load($posts, ['comments']);

    expect($posts[0]->comments)->toHaveCount(1)
        ->and($posts[1]->comments)->toHaveCount(1);
    Rest::assertSentCount(2);
    Rest::assertSent(fn ($request) => $request->uri() === 'comments' && $request->query() === ['postId' => '1']);
    Rest::assertSent(fn ($request) => $request->uri() === 'comments' && $request->query() === ['postId' => '2']);
});

it('loads nested hasMany through parent urls', function () {
    Rest::fake(['posts/*/comments' => Rest::response([['id' => 10]])]);
    $posts = eagerPosts([['id' => 1], ['id' => 2]]);

    (new EagerLoader)->load($posts, ['nestedComments']);

    expect($posts[1]->nestedComments)->toHaveCount(1);
    Rest::assertSent(fn ($request) => $request->uri() === 'posts/2/comments');
});

it('loads hasMany in one whereIn request and groups by foreign key', function () {
    Rest::fake(['comments' => Rest::response([
        ['id' => 10, 'postId' => 1],
        ['id' => 11, 'postId' => '1'],
        ['id' => 12, 'postId' => 2],
    ])]);
    $posts = eagerPosts([['id' => 1], ['id' => 2], ['id' => 3]]);

    (new EagerLoader)->load($posts, ['batchedComments']);

    expect($posts[0]->batchedComments->pluck('id')->all())->toBe([10, 11])
        ->and($posts[1]->batchedComments->pluck('id')->all())->toBe([12])
        ->and($posts[2]->batchedComments)->toHaveCount(0);
    Rest::assertSentCount(1);
    Rest::assertSent(fn ($request) => $request->query() === ['postId' => '1,2,3']);
});

it('applies constraints to each concurrent request and to the batch', function () {
    Rest::fake(['comments' => Rest::response([])]);
    $posts = eagerPosts([['id' => 1], ['id' => 2]]);
    $constraint = fn (Builder $query) => $query->orderBy('id', 'desc');

    (new EagerLoader)->load($posts, ['comments' => $constraint, 'batchedComments' => $constraint]);

    Rest::assertSent(fn ($request) => $request->query() === ['postId' => '2', 'sort' => '-id']);
    Rest::assertSent(fn ($request) => $request->query() === ['postId' => '1,2', 'sort' => '-id']);
    Rest::assertSentCount(3);
});

it('loads hasOne as the first of each group in both modes', function () {
    Rest::fake(['comments' => Rest::response([['id' => 10, 'postId' => 1], ['id' => 11, 'postId' => 1]])]);
    $posts = eagerPosts([['id' => 1], ['id' => 2]]);

    (new EagerLoader)->load($posts, ['batchedLatestComment']);

    expect($posts[0]->batchedLatestComment->id)->toBe(10)
        ->and($posts[1]->batchedLatestComment)->toBeNull();

    Rest::restore();
    Rest::fake(['comments' => Rest::response([['id' => 20, 'postId' => 1]])]);
    $posts = eagerPosts([['id' => 1]]);

    (new EagerLoader)->load($posts, ['latestComment']);

    expect($posts[0]->latestComment->id)->toBe(20);
});

it('gives keyless parents an empty hasMany without requests', function () {
    Rest::fake([]);
    $posts = eagerPosts([['title' => 'draft']]);

    (new EagerLoader)->load($posts, ['comments', 'batchedComments', 'latestComment']);

    expect($posts[0]->comments)->toHaveCount(0)
        ->and($posts[0]->batchedComments)->toHaveCount(0)
        ->and($posts[0]->latestComment)->toBeNull();
    Rest::assertSentCount(0);
});

it('loads nested relations with one round per level', function () {
    Rest::fake([
        'comments' => Rest::response([['id' => 10, 'postId' => 1, 'userId' => 7], ['id' => 11, 'postId' => 2, 'userId' => 8]]),
        'users' => Rest::response([['id' => 7, 'name' => 'Ann'], ['id' => 8, 'name' => 'Bob']]),
        'users/*' => Rest::response(['id' => 7, 'name' => 'Ann']),
    ]);
    $posts = eagerPosts([['id' => 1], ['id' => 2]]);

    (new EagerLoader)->load($posts, ['batchedComments.author']);

    expect($posts[0]->batchedComments[0]->author->name)->toBe('Ann');
    Rest::assertSentCount(3);
});

it('refuses to batch a nested relation', function (Closure $define) {
    $define(new EagerPost(['id' => 1]));
})->with([
    'batch then nested' => [fn (EagerPost $post) => $post->hasMany(EagerComment::class)->batch()->nested()],
    'nested then batch' => [fn (EagerPost $post) => $post->hasMany(EagerComment::class)->nested()->batch()],
    'has one' => [fn (EagerPost $post) => $post->hasOne(EagerComment::class)->nested()->batch()],
])->throws(InvalidArgumentException::class, 'Nested relations cannot be batched.');
