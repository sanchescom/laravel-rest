<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Httpbin;

use Sanchescom\Rest\Model;

final class Echoed extends Model
{
    protected ?string $endpoint = 'anything';

    protected ?string $dataKey = 'json';
}

final class EchoedEnvelope extends Model
{
    protected ?string $endpoint = 'anything';

    protected ?string $dataKey = 'json.data';

    protected ?string $requestDataKey = 'data';
}

final class EchoedHeaders extends Model
{
    protected ?string $endpoint = 'anything';

    protected ?string $dataKey = 'headers';
}

final class Status extends Model
{
    protected ?string $endpoint = 'status';

    protected ?string $dataKey = null;
}

final class Bearer extends Model
{
    protected ?string $endpoint = 'bearer';

    protected ?string $dataKey = null;
}

final class BasicAuth extends Model
{
    protected ?string $endpoint = 'basic-auth/live-user';

    protected ?string $dataKey = null;
}

return [
    'name' => 'httpbin',
    'docs' => 'https://httpbin.org/',
    'base_uri' => 'https://httpbin.org/',
    'throttle_ms' => 500,
    'traits' => [
        'kind' => 'echo / test service',
        'response' => 'request echo objects',
        'errors' => 'any status via /status/{code}',
        'auth' => 'bearer and basic test endpoints',
    ],
    'scenarios' => [
        'create echoes body' => ['probe' => 'write', 'op' => 'create', 'model' => Echoed::class, 'data' => ['title' => 'live']],
        'update echoes body' => ['probe' => 'write', 'op' => 'update', 'model' => Echoed::class, 'id' => 1, 'data' => ['title' => 'live']],
        'patch echoes body' => ['probe' => 'write', 'op' => 'patch', 'model' => Echoed::class, 'id' => 1, 'data' => ['title' => 'live'], 'client' => ['update_method' => 'patch']],
        'delete' => ['probe' => 'write', 'op' => 'delete', 'model' => Echoed::class, 'id' => 1],
        'request envelope' => ['probe' => 'write', 'op' => 'create', 'model' => EchoedEnvelope::class, 'data' => ['title' => 'live'], 'features' => ['read.data-key.nested']],
        'dynamic headers' => ['probe' => 'headers', 'model' => EchoedHeaders::class, 'id' => 'headers', 'headers' => ['X-Live-Check' => 'laravel-rest']],
        'bearer auth' => ['probe' => 'auth', 'model' => Bearer::class, 'client' => ['auth' => ['driver' => 'bearer', 'token' => 'live-token']], 'unauthenticated_status' => 401],
        'basic auth' => ['probe' => 'auth', 'model' => BasicAuth::class, 'id' => 'live-pass', 'client' => ['auth' => ['driver' => 'basic', 'username' => 'live-user', 'password' => 'live-pass']], 'unauthenticated_status' => 401],
        'header auth' => ['probe' => 'auth', 'model' => EchoedHeaders::class, 'id' => 'auth', 'client' => ['auth' => ['driver' => 'header', 'headers' => ['X-Api-Key' => 'live-key']]]],
        'validation error' => ['probe' => 'status', 'model' => Status::class, 'id' => 422, 'status' => 422],
        'client error' => ['probe' => 'status', 'model' => Status::class, 'id' => 418, 'status' => 418],
        'server error' => ['probe' => 'status', 'model' => Status::class, 'id' => 500, 'status' => 500],
        'missing resource' => ['probe' => 'not-found', 'model' => Status::class, 'id' => 404],
        'retry on 503' => ['probe' => 'retry', 'model' => Status::class, 'id' => 503, 'status' => 503, 'attempts' => 3, 'client' => ['retry' => ['times' => 2, 'delay' => 50]]],
        'response cache' => ['probe' => 'cache', 'kind' => 'response', 'model' => EchoedHeaders::class, 'id' => 'cache'],
    ],
];
