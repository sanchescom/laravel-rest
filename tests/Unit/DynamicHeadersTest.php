<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Tests\Support\ArrayCache;

class HeaderedPost extends Model
{
    protected ?string $dataKey = null;

    protected array $headers = ['X-Tenant-Id' => '42'];
}

class PlainHeaderPost extends Model
{
    protected ?string $dataKey = null;
}

class CachedHeaderPost extends Model
{
    protected ?string $dataKey = null;

    protected ?int $cacheTtl = 60;
}

afterEach(function () {
    Model::setCacheStore(null);
    Rest::restore();
});

function headerResolver(ClientInterface $client, ?array &$captured = null): void
{
    $resolver = new class($client, $captured) implements ClientResolverInterface
    {
        /**
         * @param  array<int, array<string, mixed>>|null  $captured
         */
        public ?array $captured;

        public function __construct(
            private readonly ClientInterface $client,
            ?array &$capturedRef,
        ) {
            $this->captured = &$capturedRef;
        }

        /**
         * @param  array<string, mixed>  $options
         */
        public function client(?string $name = null, array $options = []): ClientInterface
        {
            if ($this->captured !== null) {
                $this->captured[] = $options;
            }

            return $this->client;
        }

        public function grammar(?string $name = null): ?string
        {
            return null;
        }

        public function queryConfig(?string $name = null): array|string|null
        {
            return null;
        }
    };

    Model::setClientResolver($resolver);
}

it('passes model headers as resolver options', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->once()->andReturn(new Response(200, [], '[]'));
    $captured = [];
    headerResolver($client, $captured);

    HeaderedPost::get();

    expect($captured[0]['headers'])->toBe(['X-Tenant-Id' => '42']);
});

it('merges chain headers over model headers', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->once()->andReturn(new Response(200, [], '[]'));
    $captured = [];
    headerResolver($client, $captured);

    HeaderedPost::withHeaders(['X-Tenant-Id' => '7', 'Accept-Language' => 'de'])->get();

    expect($captured[0]['headers'])->toBe(['X-Tenant-Id' => '7', 'Accept-Language' => 'de']);
});

it('passes no header options when none are set', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->once()->andReturn(new Response(200, [], '[]'));
    $captured = [];
    headerResolver($client, $captured);

    PlainHeaderPost::get();

    expect($captured[0])->toBe([]);
});

it('caches responses separately per dynamic header set', function () {
    Model::setCacheStore(new ArrayCache);
    Rest::fake(['cached_header_posts' => Rest::response([])]);

    CachedHeaderPost::withHeaders(['Accept-Language' => 'en'])->get();
    CachedHeaderPost::withHeaders(['Accept-Language' => 'de'])->get();
    CachedHeaderPost::withHeaders(['Accept-Language' => 'en'])->get();

    Rest::assertSentCount(2);
});
