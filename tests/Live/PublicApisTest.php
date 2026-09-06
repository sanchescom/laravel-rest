<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

function liveResolver(string $baseUri, array $config = []): void
{
    $resolver = new ClientResolver([
        'live' => GuzzleClient::fromConfig(array_merge(['base_uri' => $baseUri], $config)),
    ]);
    $resolver->setDefaultClient('live');
    Model::setClientResolver($resolver);
}

class JpPost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    protected array $casts = ['id' => 'int', 'userId' => 'int'];

    public function comments(): HasMany
    {
        return $this->hasMany(JpComment::class, 'postId')->nested();
    }
}

class JpComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

it('queries jsonplaceholder with plain grammar and nested relations', function () {
    liveResolver('https://jsonplaceholder.typicode.com/');

    $posts = JpPost::where('userId', 1)->get();
    expect($posts)->toHaveCount(10);

    $post = JpPost::get(1);
    expect($post->comments)->toHaveCount(5);

    expect(fn () => JpPost::get(987654))->toThrow(ModelNotFoundException::class);
})->group('live');

it('proves bearer auth on the wire via httpbin', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://httpbin.org/',
        'auth' => ['driver' => 'bearer', 'token' => 'live-proof'],
    ]);

    $payload = json_decode((string) $client->get('bearer')->getBody(), true);

    expect($payload['authenticated'])->toBeTrue()
        ->and($payload['token'])->toBe('live-proof');
})->group('live');
