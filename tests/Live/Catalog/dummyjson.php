<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Dummyjson;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class User extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;

    public function posts(): HasMany
    {
        return $this->hasMany(PostList::class)->nested();
    }

    public function postsByUserId(): HasMany
    {
        return $this->hasMany(PostList::class, 'userId');
    }
}

final class UserList extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = 'users';
}

final class Post extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}

final class PostList extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = 'posts';

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}

final class Product extends Model
{
    protected ?string $endpoint = 'products';

    protected ?string $dataKey = null;
}

final class ProductList extends Model
{
    protected ?string $endpoint = 'products';

    protected ?string $dataKey = 'products';
}

final class ProductCreate extends Model
{
    protected ?string $endpoint = 'products/add';

    protected ?string $dataKey = null;
}

final class Http extends Model
{
    protected ?string $endpoint = 'http';

    protected ?string $dataKey = null;
}

final class AuthLogin extends Model
{
    protected ?string $endpoint = 'auth/login';

    protected ?string $dataKey = null;
}

final class AuthMe extends Model
{
    protected ?string $endpoint = 'auth';

    protected ?string $dataKey = null;
}

return [
    'name' => 'DummyJSON',
    'docs' => 'https://dummyjson.com/docs',
    'base_uri' => 'https://dummyjson.com/',
    'throttle_ms' => 700,
    'traits' => [
        'response' => 'named-key:<resource> (products/users/posts) + total/skip/limit; detail bare-object',
        'pagination' => 'offset via limit+skip, total in body',
        'sort' => 'sortBy+order',
        'keys' => 'int id',
        'relations' => 'fk attribute userId + nested url users/{id}/posts',
        'errors' => '404 {"message"}; /http/{status} synthetic',
        'writes' => 'faked (POST products/add, PUT/PATCH/DELETE products/{id})',
        'auth' => 'JWT via POST auth/login, GET auth/me',
    ],
    'client' => [
        'pagination' => ['style' => 'offset', 'total' => 'total'],
        'query' => [
            'sort' => 'separate',
            'sort_names' => ['field' => 'sortBy', 'direction' => 'order'],
            'names' => ['offset' => 'skip'],
        ],
    ],
    'scenarios' => [
        'list products' => ['probe' => 'list', 'model' => ProductList::class, 'min' => 30, 'fields' => ['id', 'title']],
        'find product' => ['probe' => 'find', 'model' => Product::class, 'id' => 1, 'fields' => ['title', 'price']],
        'get many products' => ['probe' => 'get-many', 'model' => Product::class, 'ids' => [1, 2, 3]],
        'sort users by age' => ['probe' => 'sort', 'model' => UserList::class, 'field' => 'age', 'direction' => 'asc'],
        'paginate products' => ['probe' => 'paginate', 'model' => ProductList::class, 'per_page' => 10],
        'simple paginate products' => ['probe' => 'simple-paginate', 'model' => ProductList::class, 'per_page' => 10],
        'lazy walk products' => ['probe' => 'lazy', 'model' => ProductList::class, 'chunk' => 50, 'take' => 150],
        'field filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'query.where-in', 'eager.batch'],
            'reason' => 'No generic field=value filter: posts?userId=1 is ignored (returns all 251 posts). Only path filters exist (/products/category/{c}, /users/filter?key=&value=).',
            'attempt' => ['probe' => 'filter', 'model' => PostList::class, 'field' => 'userId', 'value' => 1],
        ],
        'post author' => ['probe' => 'belongs-to', 'model' => Post::class, 'id' => 1, 'relation' => 'author', 'foreign_key' => 'userId'],
        'nested user posts' => ['probe' => 'has-many', 'model' => User::class, 'id' => 1, 'relation' => 'posts', 'nested' => true, 'foreign_key' => 'userId', 'path_suffix' => 'users/1/posts'],
        'has-many by fk' => [
            'probe' => 'unsupported',
            'features' => ['relation.has-many'],
            'reason' => 'fk filter ignored (posts?userId={id} returns all posts); use the nested users/{id}/posts relation instead.',
            'attempt' => ['probe' => 'has-many', 'model' => User::class, 'id' => 1, 'relation' => 'postsByUserId', 'foreign_key' => 'userId'],
        ],
        'eager post authors' => ['probe' => 'eager', 'model' => PostList::class, 'query' => fn (Builder $query) => $query->limit(5), 'relation' => 'author', 'mode' => 'concurrent', 'foreign_key' => 'userId'],
        'create product' => ['probe' => 'write', 'op' => 'create', 'model' => ProductCreate::class, 'data' => ['title' => 'live']],
        'update product' => ['probe' => 'write', 'op' => 'update', 'model' => Product::class, 'id' => 1, 'data' => ['title' => 'live']],
        'patch product' => ['probe' => 'write', 'op' => 'patch', 'model' => Product::class, 'id' => 1, 'data' => ['title' => 'live'], 'client' => ['update_method' => 'patch']],
        'delete product' => ['probe' => 'write', 'op' => 'delete', 'model' => Product::class, 'id' => 1],
        'missing product' => ['probe' => 'not-found', 'model' => Product::class, 'id' => 999999],
        'validation status' => ['probe' => 'status', 'model' => Http::class, 'id' => 422, 'status' => 422],
        'client status' => ['probe' => 'status', 'model' => Http::class, 'id' => 418, 'status' => 418],
        'server status' => ['probe' => 'status', 'model' => Http::class, 'id' => 500, 'status' => 500],
        'retry on 503' => ['probe' => 'retry', 'model' => Http::class, 'id' => 503, 'status' => 503, 'attempts' => 3, 'client' => ['retry' => ['times' => 2, 'delay' => 50]]],
        'bearer token from login' => [
            'probe' => 'custom',
            'features' => ['auth.bearer'],
            'run' => function (LiveContext $context) {
                $token = (new AuthLogin)->newBuilder()->post([
                    'username' => 'emilys',
                    'password' => 'emilyspass',
                    'expiresInMins' => 5,
                ])->getAttribute('accessToken');

                expect($token)->toBeString()->not->toBeEmpty();

                new LiveContext($context->slug, $context->api, ['auth' => ['driver' => 'bearer', 'token' => $token]]);

                expect((new AuthMe)->newBuilder()->get('me')->getAttribute('username'))->toBe('emilys');

                new LiveContext($context->slug, $context->api);

                $error = null;

                try {
                    (new AuthMe)->newBuilder()->get('me');
                } catch (RequestException $exception) {
                    $error = $exception;
                }

                expect($error)->not->toBeNull();
                expect($error->status)->toBe(401);
            },
        ],
        'request memo' => ['probe' => 'cache', 'kind' => 'memo', 'model' => Product::class, 'id' => 1],
    ],
];
