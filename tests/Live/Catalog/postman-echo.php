<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\PostmanEcho;

use Sanchescom\Rest\Model;

final class RootHeaders extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = 'headers';
}

final class RootBasicAuth extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = null;
}

final class RootJson extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = 'json';
}

final class RootArgs extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = 'args';
}

final class Post extends Model
{
    protected ?string $endpoint = 'post';

    protected ?string $dataKey = 'json';
}

final class PostEnvelope extends Model
{
    protected ?string $endpoint = 'post';

    protected ?string $requestDataKey = 'data';

    protected ?string $dataKey = 'json.data';
}

final class Status extends Model
{
    protected ?string $endpoint = 'status';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Postman Echo',
    'docs' => 'https://www.postman.com/postman/published-postman-templates/documentation/ae2ja6x/postman-echo',
    'base_uri' => 'https://postman-echo.com/',
    'throttle_ms' => 500,
    'traits' => [
        'kind' => 'echo',
        'response' => 'echo {args,data,json,headers,url}',
        'errors' => '/status/{code} -> {"status":code}',
        'auth' => '/basic-auth (postman:password), validated; bearer and header auth are echoed only, not validated',
        'writes' => 'verb-specific paths /post /put /patch /delete, not persisted',
    ],
    'scenarios' => [
        'echo headers' => ['probe' => 'headers', 'model' => RootHeaders::class, 'id' => 'headers', 'headers' => ['X-Live-Check' => 'laravel-rest']],
        'basic auth' => ['probe' => 'auth', 'model' => RootBasicAuth::class, 'id' => 'basic-auth', 'client' => ['auth' => ['driver' => 'basic', 'username' => 'postman', 'password' => 'password']], 'unauthenticated_status' => 401],
        'bearer echoed' => ['probe' => 'auth', 'model' => RootHeaders::class, 'id' => 'headers', 'client' => ['auth' => ['driver' => 'bearer', 'token' => 'live-token']]],
        'header auth echoed' => ['probe' => 'auth', 'model' => RootHeaders::class, 'id' => 'headers', 'client' => ['auth' => ['driver' => 'header', 'headers' => ['X-Api-Key' => 'live-key']]]],
        'create echoes body' => ['probe' => 'write', 'op' => 'create', 'model' => Post::class, 'data' => ['title' => 'live']],
        'update echoes body' => ['probe' => 'write', 'op' => 'update', 'model' => RootJson::class, 'id' => 'put', 'data' => ['title' => 'live']],
        'patch echoes body' => ['probe' => 'write', 'op' => 'patch', 'model' => RootJson::class, 'id' => 'patch', 'data' => ['title' => 'live'], 'client' => ['update_method' => 'patch']],
        'delete' => ['probe' => 'write', 'op' => 'delete', 'model' => RootJson::class, 'id' => 'delete'],
        'request envelope' => ['probe' => 'write', 'op' => 'create', 'model' => PostEnvelope::class, 'data' => ['title' => 'live']],
        'validation status' => ['probe' => 'status', 'model' => Status::class, 'id' => 422, 'status' => 422],
        'client status' => ['probe' => 'status', 'model' => Status::class, 'id' => 418, 'status' => 418],
        'server status' => ['probe' => 'status', 'model' => Status::class, 'id' => 500, 'status' => 500],
        'missing' => ['probe' => 'not-found', 'model' => Status::class, 'id' => 404],
        'retry on 503' => ['probe' => 'retry', 'model' => Status::class, 'id' => 503, 'status' => 503, 'attempts' => 3, 'client' => ['retry' => ['times' => 2, 'delay' => 50]]],
        'response cache' => ['probe' => 'cache', 'kind' => 'response', 'model' => RootArgs::class, 'id' => 'get'],
        'resource reads' => [
            'probe' => 'unsupported',
            'features' => ['read.list', 'paginate.total', 'query.filter'],
            'reason' => 'Not a resource API; there is nothing to list, paginate or filter.',
        ],
    ],
];
