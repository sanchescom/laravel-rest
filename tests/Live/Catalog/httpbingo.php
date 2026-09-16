<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Httpbingo;

use Sanchescom\Rest\Model;

final class Echoed extends Model
{
    protected ?string $endpoint = 'anything';

    protected ?string $dataKey = 'json';
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
    protected ?string $endpoint = 'basic-auth/live-user/live-pass';

    protected ?string $dataKey = null;
}

return [
    'name' => 'httpbingo',
    'docs' => 'https://httpbingo.org/',
    'base_uri' => 'https://httpbingo.org/',
    'throttle_ms' => 500,
    'traits' => [
        'kind' => 'echo / test service (go-httpbin)',
        'response' => 'request echo objects; header values are arrays (multi-value)',
        'errors' => 'any status via /status/{code}',
        'auth' => 'bearer and basic-auth/{user}/{pass} test endpoints',
    ],
    'scenarios' => [
        'create echoes body' => ['probe' => 'write', 'op' => 'create', 'model' => Echoed::class, 'data' => ['title' => 'live']],
        'dynamic headers' => ['probe' => 'headers', 'model' => EchoedHeaders::class, 'id' => 'headers', 'headers' => ['X-Live-Check' => 'laravel-rest'], 'echo' => false],
        'bearer auth' => ['probe' => 'auth', 'model' => Bearer::class, 'client' => ['auth' => ['driver' => 'bearer', 'token' => 'live-token']], 'unauthenticated_status' => 401],
        'basic auth' => ['probe' => 'auth', 'model' => BasicAuth::class, 'client' => ['auth' => ['driver' => 'basic', 'username' => 'live-user', 'password' => 'live-pass']], 'unauthenticated_status' => 401],
        'validation error' => ['probe' => 'status', 'model' => Status::class, 'id' => 422, 'status' => 422],
        'client error' => ['probe' => 'status', 'model' => Status::class, 'id' => 418, 'status' => 418],
        'server error' => ['probe' => 'status', 'model' => Status::class, 'id' => 500, 'status' => 500],
        'missing resource' => ['probe' => 'not-found', 'model' => Status::class, 'id' => 404],
        'retry on 503' => ['probe' => 'retry', 'model' => Status::class, 'id' => 503, 'status' => 503, 'attempts' => 3, 'client' => ['retry' => ['times' => 2, 'delay' => 50]]],
    ],
];
