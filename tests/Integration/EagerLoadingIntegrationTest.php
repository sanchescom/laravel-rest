<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class HttpEagerPost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function comments(): HasMany
    {
        return $this->hasMany(HttpEagerComment::class, 'postId');
    }

    public function batchedComments(): HasMany
    {
        return $this->hasMany(HttpEagerComment::class, 'postId')->batch();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(HttpEagerUser::class, 'userId');
    }

    public function batchedAuthor(): BelongsTo
    {
        return $this->belongsTo(HttpEagerUser::class, 'userId')->batch();
    }
}

class HttpEagerComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(HttpEagerUser::class, 'userId')->batch();
    }
}

class HttpEagerUser extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

beforeEach(function () {
    $resolver = new ClientResolver([
        'fixture' => GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]),
    ]);
    $resolver->setDefaultClient('fixture');
    Model::setClientResolver($resolver);
});

it('eager loads nested relations concurrently and batched over real http', function (string $comments, string $author) {
    $posts = (new HttpEagerPost)->newCollection([
        new HttpEagerPost(['id' => 1, 'userId' => 7]),
        new HttpEagerPost(['id' => 2, 'userId' => 8]),
    ]);

    $posts->load(["{$comments}.author", $author]);

    expect($posts[0]->{$comments}->pluck('id')->all())->toBe([10, 11])
        ->and($posts[1]->{$comments}->pluck('id')->all())->toBe([12])
        ->and($posts[0]->{$comments}[1]->author->name)->toBe('User 8')
        ->and($posts[1]->{$author}->name)->toBe('User 8');
})->with([
    'concurrent' => ['comments', 'author'],
    'batched' => ['batchedComments', 'batchedAuthor'],
])->group('integration');
