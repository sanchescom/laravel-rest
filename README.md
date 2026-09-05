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

## Testing Your Application

Register a mock driver for the client used in tests:

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

## Upgrading from 0.x

See [UPGRADE.md](UPGRADE.md) — 1.0 contains breaking changes.

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
