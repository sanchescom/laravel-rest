# Laravel Rest

![CI](https://github.com/sanchescom/laravel-rest/actions/workflows/ci.yml/badge.svg)

Eloquent-like models and collections for consuming REST APIs.

Define a model, point it at an endpoint, and work with remote resources the way
you work with Eloquent: `User::get()`, `User::get($id)`, `User::post([...])`,
`$user->put()`, `User::delete($id)`.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require sanchescom/laravel-rest
```

The service provider is registered automatically via package auto-discovery —
no manual registration needed.

## Quick Start

A model is a class pointed at an endpoint. Everything below runs for real
against [JSONPlaceholder](https://jsonplaceholder.typicode.com):

```php
<?php

use Sanchescom\Rest\Model;

class Post extends Model
{
    // endpoint is inferred from the class name: "posts"
    protected array $casts = ['id' => 'int', 'userId' => 'int'];
}
```

```php
// config/rest.php
'clients' => [
    'placeholder' => [
        'provider' => 'guzzle',
        'base_uri' => 'https://jsonplaceholder.typicode.com/',
    ],
],
```

```php
$post  = Post::get(1);                  // GET posts/1        -> Post
$posts = Post::get();                   // GET posts          -> Collection<Post>
$mine  = $posts->where('userId', 1);    // any Illuminate collection method
$page  = $posts->paginate(10);          // LengthAwarePaginator

$some  = Post::getMany([3, 1, 2]);      // concurrent requests, results in [3, 1, 2] order

$new   = Post::post(['title' => 'Hi', 'userId' => 1]);   // POST posts
$upd   = Post::put(1, ['title' => 'Updated']);           // PUT posts/1
Post::delete(1);                                          // DELETE posts/1

Post::get(987654);                      // 404 -> throws ModelNotFoundException
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=rest-config
```

Configure one or more clients in `config/rest.php`:

```php
<?php

return [
    'default' => env('REST_CLIENT', 'localhost'),

    'clients' => [
        'localhost' => [
            'provider' => 'guzzle',
            'base_uri' => 'https://localhost/',
            'options' => [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ],
        ],
        'billing' => [
            'provider' => 'guzzle',
            'base_uri' => 'https://billing.example.com/api/',
            'options' => [
                'headers' => [
                    'Authorization' => 'Bearer '.env('BILLING_TOKEN'),
                ],
            ],
        ],
    ],
];
```

> **Note:** `base_uri` must end with a trailing slash, and endpoints must not
> start with one — that is how Guzzle resolves relative URIs.

## Defining Models

```php
<?php

use Sanchescom\Rest\Model;

class User extends Model
{
    /** Client name from config/rest.php; null uses the default client. */
    protected ?string $client = null;

    /** Endpoint; defaults to the snake-cased plural of the class name ("users"). */
    protected ?string $endpoint = 'users';

    /** Key that wraps payloads in responses (e.g. {"data": ...}); null for bare payloads. */
    protected ?string $dataKey = 'data';

    /** Whitelist of fillable attributes; empty array allows everything. */
    protected array $fillable = ['id', 'first_name', 'last_name', 'email'];

    /** Attribute casts applied on read: int|float|bool|string. */
    protected array $casts = ['id' => 'int'];

    /** Per-model HTTP client options merged over the configured ones. */
    protected array $options = [];
}
```

## Usage

**Retrieving all models**

```php
$users = User::get(); // Sanchescom\Rest\Collection of User
```

**Retrieving a record by id**

```php
$user = User::get(1); // User
```

**Retrieving several records by id**

```php
$users = User::getMany([1, 2]); // results keep the order of the ids
```

The result is an Illuminate collection, so all the usual methods work:

```php
$bobs = User::get()->where('first_name', 'Bob');
```

**Creating**

```php
$user = User::post(['first_name' => 'Tim']);
```

**Updating**

```php
// by key
User::put(2, ['email' => 'john@foo.com']);

// or via an instance using its own primary key
$user = User::get(2);
$user->email = 'john@foo.com';
$user->put();
```

**Deleting**

```php
User::delete(1);

// or via an instance
$user = User::get(1);
$user->delete();
```

## Error Handling

Every 4xx/5xx response throws a typed exception; you never get a silent null:

| Status | Exception |
| --- | --- |
| 404 | `Sanchescom\Rest\Exceptions\ModelNotFoundException` |
| 422 | `Sanchescom\Rest\Exceptions\ValidationException` (`->errors()`) |
| 500+ | `Sanchescom\Rest\Exceptions\ServerException` |
| other 4xx | `Sanchescom\Rest\Exceptions\RequestException` |

All of them extend `Sanchescom\Rest\Exceptions\RestException` and expose the
request context: `$e->uri`, `$e->status`, `$e->body`.

```php
use Sanchescom\Rest\Exceptions\ValidationException;

try {
    User::post(['email' => 'not-an-email']);
} catch (ValidationException $e) {
    $errors = $e->errors();
}
```

## Pagination

Collections can be paginated in memory:

```php
$paginator = User::get()->paginate(15); // Illuminate LengthAwarePaginator
```

## Query Builder

Filter, sort, and paginate without writing query strings by hand:

```php
// Plain grammar (default) — ?status=active&sort=-created_at&limit=20
$posts = Post::where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

// Pagination
Post::page(2)->limit(15)->get();   // ?page=2&limit=15
Post::offset(30)->limit(15)->get(); // ?offset=30&limit=15

// Extra / non-standard params
Post::withQuery(['include' => 'author'])->get();

// Convenience
Post::where('status', 'draft')->first(); // first item of the collection
Post::where('userId', 1)->count();       // count of matching items
```

**JSON:API grammar** — produces `filter[field]`, `page[size]`, `page[number]`:

```php
// Per-client (config/rest.php):
'clients' => [
    'myapi' => [
        'provider' => 'guzzle',
        'base_uri'  => 'https://api.example.com/',
        'grammar'   => \Sanchescom\Rest\Query\JsonApiGrammar::class,
    ],
],

// Per-model (overrides the client-level setting):
class Article extends Model
{
    protected ?string $grammar = \Sanchescom\Rest\Query\JsonApiGrammar::class;
}
```

Grammars implement `Sanchescom\Rest\Query\Grammar` — use a custom class for
any other query convention. See [docs/capabilities.md](docs/capabilities.md).

## Caching

laravel-rest can cache GET responses so that repeat reads within a TTL window
cost zero HTTP round-trips. Caching is opt-in per model or per chain; write
operations always pass through and automatically invalidate the model's cache on
success.

### Quick start — Laravel app

Publish the config and enable the cache store you want (any configured Laravel
cache store works):

```php
// config/rest.php
'cache' => [
    'store' => null,   // null = default Laravel store; 'redis', 'memcached', etc.
    'ttl'   => 300,    // default TTL in seconds used by withCache() without args
],
```

Set `'cache' => false` to skip store wiring entirely (no `Model::setCacheStore`
call is made at boot).

Then enable caching on the models you want cached:

```php
class Post extends Model
{
    protected ?int $cacheTtl = 60; // seconds; null (default) = caching disabled
}
```

### Per-chain opt-in and opt-out

Any chain can override the model setting:

```php
// Enable caching for one chain (uses model $cacheTtl, or default TTL if not set)
Post::withCache()->get();

// Use a specific TTL just for this chain
Post::withCache(30)->get();

// Force a fresh hit even if the model has $cacheTtl
Post::withoutCache()->get();
```

### Page-flip scenario — zero HTTP on repeat

Because cache keys encode `client | model | version | uri | compiled-query`,
every unique page is cached independently. Flipping back to a page that was
already fetched costs nothing:

```php
$page1a = Post::withCache()->page(1)->get(); // HTTP request
$page2  = Post::withCache()->page(2)->get(); // HTTP request
$page1b = Post::withCache()->page(1)->get(); // cache hit — zero HTTP
```

### Write-through invalidation

Successful `post`, `put`, and `delete` calls bump the model's internal cache
version, making all previously cached entries for that model stale. The next
read transparently refetches from the API.

Cancelled writes (a `creating`/`updating`/`deleting` listener returning `false`)
do **not** bump the version.

```php
Post::post(['title' => 'New']);  // POST + flushCache() on success
Post::put(1, ['title' => 'Hi']); // PUT  + flushCache() on success
Post::delete(1);                 // DELETE + flushCache() on success
```

You can also flush explicitly at any time (O(1) version bump — does not iterate
cache keys):

```php
Post::flushCache(); // safe no-op when no store is configured
```

> **External mutations are invisible.** If another service creates, updates, or
> deletes records via the same API, this package has no way to know. Keep `$cacheTtl`
> short enough for your consistency requirements, or call `Post::flushCache()`
> when you know a mutation happened externally.

### Fail loud — no silent cache misses

If caching is requested (via `$cacheTtl` or `withCache()`) but no store has
been configured, the builder throws a `RestException` immediately rather than
silently falling back to a live request:

```php
Model::setCacheStore(null);
CachedPost::get(); // throws RestException: "no cache store configured"
```

### Standalone use (outside Laravel)

Wire the store directly — any PSR-16 `CacheInterface` works:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Sanchescom\Rest\Model;

Model::setCacheStore(
    new Psr16Cache(new FilesystemAdapter),
    defaultTtl: 120,
);

// Now any model with $cacheTtl or withCache() will use the file cache
Post::withCache(60)->get(1);
```

`psr/simple-cache` is a suggested dependency; install it alongside whichever
PSR-16 adapter you choose.

### Testing with fakes

`Rest::fake()` and caching compose correctly. The fake is the inner client;
`CachingClient` wraps it. Use `Rest::assertSentCount()` to prove cache hits
(only the first call is forwarded to the fake):

```php
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Model;

Model::setCacheStore(new \Sanchescom\Rest\Tests\Support\ArrayCache);

Rest::fake(['posts' => Rest::response([['id' => 1]])]);

Post::withCache()->page(1)->get();
Post::withCache()->page(2)->get();
Post::withCache()->page(1)->get(); // cache hit

Rest::assertSentCount(2); // page1 + page2 — page1 repeat was served from cache

Rest::restore();
Model::setCacheStore(null);
```

## Relations

Three relation types, all lazy-loaded and per-instance cached on first access:

```php
class Post extends Model
{
    // FK filter: GET comments?postId=1
    public function comments(): \Sanchescom\Rest\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Nested URL: GET posts/1/thumbnail
    public function thumbnail(): \Sanchescom\Rest\Relations\HasOne
    {
        return $this->hasOne(Thumbnail::class)->nested();
    }

    // Parent lookup: GET users/{post->userId}
    public function author(): \Sanchescom\Rest\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

$post = Post::get(1);
$post->comments;          // Collection<Comment>  — loaded once, cached
$post->thumbnail;         // ?Thumbnail
$post->author;            // ?User

// Relations also chain onto the builder:
$post->comments()->where('approved', true)->get();
```

Foreign-key name defaults to camelCase class name + `Id`
(`postId` for `Post`, `userId` for `User`). Pass the key explicitly to
override: `$this->hasMany(Comment::class, 'post_id')`.

## Authentication

Configure an `auth` block inside a client entry:

```php
// Bearer token
'auth' => ['driver' => 'bearer', 'token' => env('API_TOKEN')],

// HTTP Basic
'auth' => ['driver' => 'basic', 'username' => '…', 'password' => '…'],

// Arbitrary header(s) — name must match what the API expects exactly
'auth' => [
    'driver'  => 'header',
    'headers' => ['X-API-Key' => env('API_KEY')],
],

// Custom driver — any class implementing AuthInterface
'auth' => ['driver' => \App\Auth\HmacAuth::class, 'secret' => env('HMAC_SECRET')],
```

Custom drivers receive the full `auth` config array as the constructor
argument and must implement `Sanchescom\Rest\Auth\AuthInterface`.

An `InvalidArgumentException` is thrown immediately on boot for unknown
drivers or missing required keys, not at request time.

## Retry

Add a `retry` block to a client config:

```php
'retry' => [
    'times'              => 3,       // max attempts (excluding the first)
    'delay'              => 100,     // base delay in ms
    'multiplier'         => 2.0,     // exponential multiplier
    'statuses'           => [429, 500, 502, 503, 504],
    'respect_retry_after' => true,   // honour Retry-After response header
],
```

Connection errors (`ConnectException`) are always retried regardless of
`statuses`. There is no circuit breaker or jitter — reach out or open a PR if
you need either.

## Model Events

Six hooks fire around write operations. Returning `false` from a `creating`,
`updating`, or `deleting` listener cancels the operation (`post`/`put` return
`null`; `delete` returns `false`):

```php
Post::creating(function (Post $post): bool|void {
    if ($post->title === '') {
        return false; // cancel
    }
});

Post::created(function (Post $post): void {
    Cache::forget('posts');
});

Post::updating(fn (Post $post) => /* return false to cancel */ null);
Post::updated(fn (Post $post) => null);
Post::deleting(fn (Post $post) => /* return false to cancel */ null);
Post::deleted(fn (Post $post) => null);
```

**Laravel event dispatcher bridge** — if you set a dispatcher on the model,
`created`, `updated`, and `deleted` also dispatch
`Sanchescom\Rest\Events\ModelCreated`,
`Sanchescom\Rest\Events\ModelUpdated`, and
`Sanchescom\Rest\Events\ModelDeleted` (each carries the model as a public
`$model` property):

```php
use Illuminate\Events\Dispatcher;

Model::setEventDispatcher(app(Dispatcher::class));
```

Clear listeners between tests with `Post::flushEventListeners()`.

## Testing Your Application

`Rest::fake()` is the primary testing path. It swaps the entire HTTP layer
with a fake that records every request and lets you assert against it.

```php
use Sanchescom\Rest\Rest;

Rest::fake([
    'posts'   => Rest::response(['id' => 1, 'title' => 'Hello']),
    'posts/*' => Rest::response(['id' => 2, 'title' => 'Updated']),
]);

$post = Post::get(1);                  // served from the fake
Post::post(['title' => 'Hello']);

Rest::assertSentCount(2);

Rest::assertSent(function ($request) {
    return $request->method() === 'GET' && $request->uri() === 'posts';
});

Rest::assertNotSent(function ($request) {
    return $request->method() === 'DELETE';
});

// Inspect all recorded requests
$requests = Rest::recorded(); // list<RecordedRequest>

// Restore the real resolver in teardown
Rest::restore();
```

`RecordedRequest` exposes `method()`, `uri()`, `query()`, and `data()`.

Patterns in the map use `Str::is()` matching (wildcards with `*`). Responses
with a 4xx/5xx status code cause the fake to throw the same typed exception as
the real client would.

> **Important:** `Rest` uses PHPUnit's `Assert` internally. It is a
> test-context class — do not call `Rest::assertSent*` or `Rest::recorded()`
> in production code.

**Advanced alternative: `extend('mock')`** for full Guzzle handler control:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Clients\GuzzleClient;

config()->set('rest.default', 'testing');
config()->set('rest.clients.testing', ['provider' => 'mock']);

app('rest')->extend('mock', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"data":[{"id":1}]}'),
    ]);

    return new GuzzleClient(new Client([
        'handler' => HandlerStack::create($mock),
        'base_uri' => 'https://api.test/',
        'http_errors' => false,
    ]));
});

$users = User::get(); // served from the mock
```

## Usage Outside Laravel

The package works without a booted Laravel app (only `paginate()` and config
publishing need one). Wire the resolver yourself:

```php
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;

$resolver = new ClientResolver([
    'placeholder' => GuzzleClient::fromConfig([
        'base_uri' => 'https://jsonplaceholder.typicode.com/',
        'options' => ['headers' => ['Accept' => 'application/json']],
    ]),
]);
$resolver->setDefaultClient('placeholder');

Model::setClientResolver($resolver);

Post::get(1); // works — no Laravel container involved
```

## Capability Matrix

See [docs/capabilities.md](docs/capabilities.md) for a full breakdown of what
is supported, what is not, and how to extend it.

## Upgrading

See [UPGRADE.md](UPGRADE.md) for breaking-change notes between every major/minor version.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of
conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available,
see the [tags on this repository](https://github.com/sanchescom/laravel-rest/tags).

## Authors

* **Efimov Aleksandr** - *Initial work* - [Sanchescom](https://github.com/sanchescom)

See also the list of [contributors](https://github.com/sanchescom/laravel-rest/contributors)
who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
