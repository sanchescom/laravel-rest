<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Rest;

class RelPost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function comments(): HasMany
    {
        return $this->hasMany(RelComment::class, 'postId');
    }

    public function nestedComments(): HasMany
    {
        return $this->hasMany(RelComment::class)->nested();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(RelUser::class, 'userId');
    }
}

class RelComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

class RelUser extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

afterEach(fn () => Rest::restore());

it('loads hasMany through a fk filter', function () {
    Rest::fake(['comments' => Rest::response([['id' => 10, 'postId' => 1]])]);

    $comments = (new RelPost(['id' => 1]))->comments;

    expect($comments)->toHaveCount(1);
    Rest::assertSent(fn ($request) => $request->uri() === 'comments'
        && $request->query() === ['postId' => 1]);
});

it('loads hasMany through a nested url', function () {
    Rest::fake(['posts/1/comments' => Rest::response([['id' => 10]])]);

    $comments = (new RelPost(['id' => 1]))->nestedComments;

    expect($comments)->toHaveCount(1);
    Rest::assertSent(fn ($request) => $request->uri() === 'posts/1/comments');
});

it('caches lazy relations per instance', function () {
    Rest::fake(['comments' => Rest::response([['id' => 10]])]);

    $post = new RelPost(['id' => 1]);
    $post->comments;
    $post->comments;

    Rest::assertSentCount(1);
});

it('chains where through the relation', function () {
    Rest::fake(['comments' => Rest::response([])]);

    (new RelPost(['id' => 1]))->comments()->where('rating', 5)->get();

    Rest::assertSent(fn ($request) => $request->query() === ['postId' => 1, 'rating' => 5]);
});

it('loads belongsTo by foreign key', function () {
    Rest::fake(['users/7' => Rest::response(['id' => 7, 'name' => 'Tim'])]);

    $author = (new RelPost(['id' => 1, 'userId' => 7]))->author;

    expect($author->name)->toBe('Tim');
});

it('returns null belongsTo when fk is absent', function () {
    Rest::fake(['users/*' => Rest::response([])]);

    expect((new RelPost(['id' => 1]))->author)->toBeNull();
    Rest::assertSentCount(0);
});

it('returns null belongsTo repeatedly without http', function () {
    Rest::fake(['users/*' => Rest::response([])]);

    $post = new RelPost(['id' => 1]);

    expect($post->author)->toBeNull()
        ->and($post->author)->toBeNull();
    Rest::assertSentCount(0);
});

it('does not treat regular model methods as relations', function () {
    Rest::fake([]);

    expect((new RelPost(['id' => 1]))->fill)->toBeNull();
    Rest::assertSentCount(0);
});
