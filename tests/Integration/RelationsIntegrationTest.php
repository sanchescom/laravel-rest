<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class LivePost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function comments(): HasMany
    {
        return $this->hasMany(LiveComment::class, 'postId');
    }

    public function nestedComments(): HasMany
    {
        return $this->hasMany(LiveComment::class)->nested();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(LiveUser::class, 'userId');
    }
}

class LiveComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

class LiveUser extends Model
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

it('loads fk-filtered and nested relations over real http', function () {
    $post = new LivePost(['id' => 1, 'userId' => 7]);

    expect($post->comments->pluck('id')->all())->toBe([10, 11])
        ->and($post->nestedComments)->toHaveCount(2)
        ->and($post->author->name)->toBe('User 7');
})->group('integration');
