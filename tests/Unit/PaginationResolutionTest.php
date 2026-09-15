<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class ResolvedPagedPost extends Model
{
    protected ?string $endpoint = 'resolved_paged_posts';
}

class PropertyPagedPost extends Model
{
    protected array|string|null $pagination = ['total' => 'count', 'style' => 'offset'];
}

function paginationResolver(array|string|null $pagination): ClientResolver
{
    $resolver = new ClientResolver(['main' => Mockery::mock(ClientInterface::class)]);
    $resolver->setDefaultClient('main');

    if ($pagination !== null) {
        $resolver->setPaginationConfig('main', $pagination);
    }

    Model::setClientResolver($resolver);

    return $resolver;
}

afterEach(function () {
    Rest::restore();
    Model::unsetClientResolver();
});

it('reads pagination config from the client resolver', function () {
    paginationResolver('laravel');

    expect((new ResolvedPagedPost)->getPaginationConfig()?->total)->toBe('meta.total');
});

it('prefers the model property over the client config', function () {
    paginationResolver('laravel');

    $config = (new PropertyPagedPost)->getPaginationConfig();

    expect($config?->total)->toBe('count')
        ->and($config?->next)->toBeNull()
        ->and($config?->style)->toBe('offset');
});

it('returns null when nothing is configured', function () {
    paginationResolver(null);

    expect((new ResolvedPagedPost)->getPaginationConfig())->toBeNull();
});

it('returns null for resolvers without pagination support', function () {
    Model::setClientResolver(new class implements ClientResolverInterface
    {
        public function client(?string $name = null, array $options = []): ClientInterface
        {
            return Mockery::mock(ClientInterface::class);
        }

        public function grammar(?string $name = null): ?string
        {
            return null;
        }

        public function queryConfig(?string $name = null): array|string|null
        {
            return null;
        }
    });

    expect((new ResolvedPagedPost)->getPaginationConfig())->toBeNull();
});

it('keeps the previous resolver pagination config under fakes', function () {
    paginationResolver('laravel');

    Rest::fake(['resolved_paged_posts' => Rest::response([])]);

    expect((new ResolvedPagedPost)->getPaginationConfig()?->total)->toBe('meta.total');
});

it('has no pagination config under fakes without a previous resolver', function () {
    Model::unsetClientResolver();

    Rest::fake(['resolved_paged_posts' => Rest::response([])]);

    expect((new ResolvedPagedPost)->getPaginationConfig())->toBeNull();
});
