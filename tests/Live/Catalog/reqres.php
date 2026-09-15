<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Reqres;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class User extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = 'data';
}

final class UserWrite extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

final class UserEnvelope extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $requestDataKey = 'data';

    protected ?string $dataKey = 'data';
}

return [
    'name' => 'ReqRes',
    'docs' => 'https://reqres.in/',
    'base_uri' => 'https://reqres.in/api/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'data (list and detail) + support/_meta noise',
        'pagination' => 'page + per_page, total in body',
        'keys' => 'int id',
        'errors' => '404 {}; 400 {"error"}; 403 invalid_api_key',
        'writes' => 'faked, echoes body (flat, no envelope on update/patch)',
        'auth' => 'x-api-key header (public free key reqres-free-v1; wrong key -> 403, no key -> 200)',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'total'],
        'query' => ['names' => ['limit' => 'per_page']],
    ],
    'scenarios' => [
        'list users' => ['probe' => 'list', 'model' => User::class, 'min' => 6, 'fields' => ['id', 'email']],
        'find user' => ['probe' => 'find', 'model' => User::class, 'id' => 2, 'fields' => ['email']],
        'get many users' => ['probe' => 'get-many', 'model' => User::class, 'ids' => [1, 2, 3]],
        'paginate users' => ['probe' => 'paginate', 'model' => User::class, 'per_page' => 4],
        'simple paginate users' => ['probe' => 'simple-paginate', 'model' => User::class, 'per_page' => 5, 'last_page' => 3],
        'lazy walk users' => ['probe' => 'lazy', 'model' => User::class, 'chunk' => 5, 'take' => 10],
        'filters and sort' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'query.sort', 'query.where-in'],
            'reason' => 'No query filters or sorting on any endpoint.',
        ],
        'relations' => [
            'probe' => 'unsupported',
            'features' => ['relation.belongs-to', 'relation.has-many', 'relation.nested'],
            'reason' => 'No related resources exposed by the API.',
        ],
        'missing user' => ['probe' => 'not-found', 'model' => User::class, 'id' => 23],
        'create user with envelope' => ['probe' => 'write', 'op' => 'create', 'model' => UserEnvelope::class, 'data' => ['name' => 'live']],
        'update user' => ['probe' => 'write', 'op' => 'update', 'model' => UserWrite::class, 'id' => 2, 'data' => ['name' => 'live']],
        'patch user' => ['probe' => 'write', 'op' => 'patch', 'model' => UserWrite::class, 'id' => 2, 'data' => ['name' => 'live'], 'client' => ['update_method' => 'patch']],
        'delete user' => ['probe' => 'write', 'op' => 'delete', 'model' => UserWrite::class, 'id' => 2],
        'api key header' => ['probe' => 'auth', 'model' => User::class, 'id' => 2, 'client' => ['auth' => ['driver' => 'header', 'headers' => ['x-api-key' => 'reqres-free-v1']]]],
        'rejected api key' => [
            'probe' => 'status',
            'model' => User::class,
            'status' => 403,
            'client' => ['auth' => ['driver' => 'header', 'headers' => ['x-api-key' => 'definitely-wrong']]],
            'query' => fn (Builder $query) => $query->where('cache_bust', (string) random_int(100000, 999999)),
        ],
        'response cache' => ['probe' => 'cache', 'kind' => 'response', 'model' => User::class, 'id' => 2],
    ],
];
