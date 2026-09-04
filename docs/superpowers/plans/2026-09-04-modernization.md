# Laravel Rest Modernization Implementation Plan (Phases 1–3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize `sanchescom/laravel-rest` to PHP 8.2+ / Laravel 11–12, fix all known bugs (client cache races, endpoint mutation, silent HTTP errors, broken autoload), replace abandoned dependencies, and cover everything with Pest tests + CI.

**Architecture:** A REST client package exposing an Eloquent-like Model API. `Model` (own attribute layer, no external model lib) forwards calls to `Builder`, which builds URIs and hydrates responses. `ClientManager` resolves configured `ClientInterface` implementations (Guzzle driver) — clients are stateless: endpoint/URI is passed per request, never stored on the client. HTTP errors map to a typed exception hierarchy.

**Tech Stack:** PHP ^8.2, illuminate/support+container+pagination ^11|^12, Guzzle ^7.8, Pest v3, Orchestra Testbench, PHPStan, Laravel Pint.

**Spec:** No separate spec file — requirements captured from the review discussion; the authoritative list is the Global Constraints below plus per-task behavior tests. Phases 4 (features: query builder, pagination, auth, retry, fakes, events, relations) and 5 (release) will get a separate plan after this one ships.

## Global Constraints

- PHP `^8.2`; Laravel components `^11.0|^12.0` (individual `illuminate/*` packages, NOT `laravel/framework`).
- License: MIT everywhere (composer.json currently says GPL-3.0 — that is wrong; LICENSE.md is MIT).
- `declare(strict_types=1)` in every PHP file; native parameter/return types; `final` for concrete classes without extension points.
- No `dd`/`dump`, no `env()` outside config, no Laravel `response()` helper inside the package (must work without a booted app except for `Collection::paginate` and the ServiceProvider).
- Drop dependencies: `jenssegers/model`, `sanchescom/json-helper`, `laravel/framework` pin.
- Clients are stateless: no `setEndpoint()` on clients; URIs passed per call. `base_uri` in config must end with `/`; endpoints have no leading slash (Guzzle relative-URI resolution).
- PSR-4: `Sanchescom\Rest\` → `src/` with classes at `src/*.php` (old layout `src/Rest/*` violated the mapping — autoload was broken).
- Every 4xx/5xx response throws: 404 → `ModelNotFoundException`, 422 → `ValidationException`, ≥500 → `ServerException`, other 4xx → `RequestException`. Guzzle runs with `http_errors => false`; the package inspects status itself.
- `getMany()` results MUST preserve the order of the input ids.
- Commit style: conventional commits (`feat:`, `fix:`, `test:`, `chore:`, `docs:`).
- Verification for every task: `composer test` (Pest), `composer lint` (Pint), `composer analyse` (PHPStan level 6).

---

### Task 1: Scaffolding — composer, tooling, clean slate

**Files:**
- Modify: `composer.json` (full rewrite)
- Delete: `composer.lock`, `src/Rest/` (entire old tree — history stays in git)
- Create: `pint.json`, `phpstan.neon`, `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`, `.gitignore` (extend), `.editorconfig`

**Interfaces:**
- Consumes: nothing.
- Produces: working `composer install`, `composer test` (0 tests, exit 0), `composer lint`, `composer analyse`; PSR-4 `Sanchescom\Rest\` → `src/`, `Sanchescom\Rest\Tests\` → `tests/`.

- [ ] **Step 1: Rewrite composer.json**

```json
{
    "name": "sanchescom/laravel-rest",
    "description": "Eloquent-like models and collections for consuming REST APIs.",
    "license": "MIT",
    "keywords": ["laravel", "rest", "api", "client", "model"],
    "authors": [
        {"name": "Alexander Efimov", "email": "sanches.com@mail.ru"}
    ],
    "require": {
        "php": "^8.2",
        "guzzlehttp/guzzle": "^7.8",
        "illuminate/container": "^11.0|^12.0",
        "illuminate/pagination": "^11.0|^12.0",
        "illuminate/support": "^11.0|^12.0"
    },
    "require-dev": {
        "laravel/pint": "^1.18",
        "orchestra/testbench": "^9.9|^10.0",
        "pestphp/pest": "^3.0",
        "phpstan/phpstan": "^2.0"
    },
    "autoload": {
        "psr-4": {"Sanchescom\\Rest\\": "src/"}
    },
    "autoload-dev": {
        "psr-4": {"Sanchescom\\Rest\\Tests\\": "tests/"}
    },
    "scripts": {
        "test": "./vendor/bin/pest",
        "lint": "./vendor/bin/pint --test",
        "fix": "./vendor/bin/pint",
        "analyse": "./vendor/bin/phpstan analyse"
    },
    "extra": {
        "laravel": {"providers": ["Sanchescom\\Rest\\RestServiceProvider"]}
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {"pestphp/pest-plugin": true}
    },
    "minimum-stability": "stable"
}
```

- [ ] **Step 2: Delete old code and lock**

```bash
git rm -r src/Rest composer.lock
```

- [ ] **Step 3: Create tooling configs**

`pint.json`:
```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "final_class": false
    }
}
```

`phpstan.neon`:
```neon
parameters:
    level: 6
    paths:
        - src
```

`phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

`tests/Pest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\TestCase;

uses(TestCase::class)->in('Feature');
```

`tests/TestCase.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sanchescom\Rest\RestServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class];
    }
}
```

Also create empty dirs `tests/Unit/` and `tests/Feature/` (add `.gitkeep`), `src/.gitkeep`.

Note: `tests/TestCase.php` references `RestServiceProvider` which is rewritten in Task 9; until then only Unit suite runs — that is fine, Feature dir is empty.
To keep `composer install` green before Task 9 exists, create a minimal placeholder provider now:

`src/RestServiceProvider.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\ServiceProvider;

class RestServiceProvider extends ServiceProvider
{
}
```

- [ ] **Step 4: Fix license mismatch and .gitignore**

`.gitignore` gets: `vendor/`, `composer.lock`, `.phpunit.cache/`, `.phpunit.result.cache`.
LICENSE.md stays MIT; composer.json now says MIT — mismatch resolved.

- [ ] **Step 5: Install and verify**

Run: `composer install`, then `composer test` (expect "no tests executed", exit 0), `composer lint`, `composer analyse` (expect pass on the placeholder provider).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "chore: modernize toolchain to php 8.2, laravel 11/12, pest 3"
```

---

### Task 2: Exception hierarchy

**Files:**
- Create: `src/Exceptions/RestException.php`, `src/Exceptions/RequestException.php`, `src/Exceptions/ModelNotFoundException.php`, `src/Exceptions/ValidationException.php`, `src/Exceptions/ServerException.php`
- Test: `tests/Unit/ExceptionsTest.php`

**Interfaces:**
- Produces: `RequestException::fromStatus(string $uri, int $status, array $body = []): RequestException` (returns the mapped subclass); public readonly `$uri`, `$status`, `$body`; `ValidationException::errors(): array`.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Exceptions\ValidationException;

it('maps status codes to exception subclasses', function (int $status, string $class) {
    expect(RequestException::fromStatus('users/1', $status))->toBeInstanceOf($class);
})->with([
    [404, ModelNotFoundException::class],
    [422, ValidationException::class],
    [500, ServerException::class],
    [503, ServerException::class],
    [400, RequestException::class],
    [403, RequestException::class],
]);

it('exposes uri, status and body', function () {
    $e = RequestException::fromStatus('users/1', 400, ['message' => 'Bad']);
    expect($e->uri)->toBe('users/1')
        ->and($e->status)->toBe(400)
        ->and($e->body)->toBe(['message' => 'Bad'])
        ->and($e->getMessage())->toContain('users/1')
        ->and($e)->toBeInstanceOf(RestException::class);
});

it('exposes validation errors', function () {
    $e = RequestException::fromStatus('users', 422, ['errors' => ['email' => ['Invalid']]]);
    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($e->errors())->toBe(['email' => ['Invalid']]);
});
```

- [ ] **Step 2: Run** `./vendor/bin/pest tests/Unit/ExceptionsTest.php` — expect FAIL (classes not found).

- [ ] **Step 3: Implement**

`src/Exceptions/RestException.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Exceptions;

use RuntimeException;

class RestException extends RuntimeException
{
}
```

`src/Exceptions/RequestException.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Exceptions;

class RequestException extends RestException
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly string $uri,
        public readonly int $status,
        public readonly array $body = [],
    ) {
        parent::__construct("REST request to [{$uri}] failed with status {$status}.");
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromStatus(string $uri, int $status, array $body = []): self
    {
        return match (true) {
            $status === 404 => new ModelNotFoundException($uri, $status, $body),
            $status === 422 => new ValidationException($uri, $status, $body),
            $status >= 500 => new ServerException($uri, $status, $body),
            default => new self($uri, $status, $body),
        };
    }
}
```

`ModelNotFoundException`, `ServerException` — empty subclasses of `RequestException` (same file pattern as `RestException`).

`src/Exceptions/ValidationException.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Exceptions;

class ValidationException extends RequestException
{
    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return (array) ($this->body['errors'] ?? []);
    }
}
```

- [ ] **Step 4: Run tests — expect PASS.** Then `composer lint && composer analyse`.

- [ ] **Step 5: Commit** — `git add -A && git commit -m "feat: typed http exception hierarchy"`

---

### Task 3: Json support class (drop sanchescom/json-helper)

**Files:**
- Create: `src/Support/Json.php`
- Test: `tests/Unit/JsonTest.php`

**Interfaces:**
- Produces: `Json::decode(string $json): array` — throws `RestException` on invalid JSON or non-array payload.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Support\Json;

it('decodes json objects and arrays to arrays', function () {
    expect(Json::decode('{"a":1}'))->toBe(['a' => 1])
        ->and(Json::decode('[1,2]'))->toBe([1, 2]);
});

it('throws on malformed json', function () {
    Json::decode('{oops');
})->throws(RestException::class);

it('throws on scalar payloads', function () {
    Json::decode('"just a string"');
})->throws(RestException::class);
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Support;

use JsonException;
use Sanchescom\Rest\Exceptions\RestException;

final class Json
{
    /**
     * @return array<mixed>
     */
    public static function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RestException("Unable to decode response JSON: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($decoded)) {
            throw new RestException('Response JSON must decode to an array.');
        }

        return $decoded;
    }
}
```

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: json decoding without external helper"`

---

### Task 4: Model attribute layer (replaces jenssegers/model)

**Files:**
- Create: `src/Model.php` (attribute half; REST half added in Task 7)
- Test: `tests/Unit/ModelAttributesTest.php`

**Interfaces:**
- Produces: `__construct(array $attributes = [])`, `fill(array): static`, `getAttribute(string): mixed`, `setAttribute(string, mixed): void`, `getAttributes(): array`, `toArray(): array`, `newInstance(array): static`, `getKey(): mixed`, `getKeyName(): string`; magic `__get/__set/__isset/__unset`; `ArrayAccess`, `JsonSerializable`, `Illuminate\Contracts\Support\Arrayable`. Protected props for subclasses: `$fillable`, `$casts` (`int|float|bool|string`), `$primaryKey = 'id'`.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;

class GuardedStub extends Model
{
    protected array $fillable = ['name', 'age'];

    protected array $casts = ['age' => 'int', 'active' => 'bool'];
}

class OpenStub extends Model
{
}

it('fills only fillable attributes when fillable is set', function () {
    $m = new GuardedStub(['name' => 'Tim', 'secret' => 'x']);
    expect($m->name)->toBe('Tim')->and($m->secret)->toBeNull();
});

it('fills everything when fillable is empty', function () {
    $m = new OpenStub(['anything' => 'goes']);
    expect($m->anything)->toBe('goes');
});

it('casts attributes on read', function () {
    $m = new GuardedStub(['age' => '30']);
    expect($m->age)->toBeIdentical(30);
});

it('leaves null uncast', function () {
    expect((new GuardedStub())->age)->toBeNull();
});

it('supports magic set, isset, unset', function () {
    $m = new OpenStub();
    $m->name = 'Bob';
    expect(isset($m->name))->toBeTrue();
    unset($m->name);
    expect(isset($m->name))->toBeFalse();
});

it('supports array access and json serialization', function () {
    $m = new OpenStub(['id' => 1]);
    expect($m['id'])->toBe(1)
        ->and(json_encode($m))->toBe('{"id":1}')
        ->and($m->toArray())->toBe(['id' => 1]);
});

it('returns primary key via getKey', function () {
    expect((new OpenStub(['id' => 7]))->getKey())->toBe(7)
        ->and((new OpenStub())->getKey())->toBeNull();
});

it('applies casts in toArray', function () {
    expect((new GuardedStub(['age' => '30']))->toArray())->toBe(['age' => 30]);
});
```

(If Pest lacks `toBeIdentical`, use `->toBe(30)` — `toBe` is strict.)

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class Model implements Arrayable, ArrayAccess, JsonSerializable
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<int, string> */
    protected array $fillable = [];

    /** @var array<string, string> */
    protected array $casts = [];

    protected string $primaryKey = 'id';

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    protected function isFillable(string $key): bool
    {
        return $this->fillable === [] || in_array($key, $this->fillable, true);
    }

    public function getAttribute(string $key): mixed
    {
        return $this->castAttribute($key, $this->attributes[$key] ?? null);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || ! isset($this->casts[$key])) {
            return $value;
        }

        return match ($this->casts[$key]) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach (array_keys($this->attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function newInstance(array $attributes = []): static
    {
        return new static($attributes);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
```

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: own model attribute layer, drop jenssegers/model"`

---

### Task 5: ClientInterface + GuzzleClient (stateless, PSR-7, ordered getMany)

**Files:**
- Create: `src/Contracts/ClientInterface.php`, `src/Clients/GuzzleClient.php`
- Test: `tests/Unit/GuzzleClientTest.php`

**Interfaces:**
- Produces:
```php
interface ClientInterface
{
    public function get(string $uri, array $query = []): ResponseInterface;

    /** @param array<int|string, string> $uris
     *  @return array<int|string, ResponseInterface> same keys/order as $uris */
    public function getMany(array $uris): array;

    public function post(string $uri, array $data = []): ResponseInterface;

    public function put(string $uri, array $data = []): ResponseInterface;

    public function delete(string $uri): ResponseInterface;
}
```
- `GuzzleClient::__construct(GuzzleHttp\Client $client)`; `GuzzleClient::fromConfig(array $config): self` where config = `['base_uri' => ..., 'options' => [...]]`. `http_errors` forced to `false`; every response with status ≥ 400 throws via `RequestException::fromStatus()`.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Exceptions\ValidationException;

function makeClient(array $responses, ?array &$history = null): GuzzleClient
{
    $stack = HandlerStack::create(new MockHandler($responses));

    if ($history !== null) {
        $stack->push(Middleware::history($history));
    }

    return new GuzzleClient(new Client([
        'handler' => $stack,
        'base_uri' => 'https://api.test/',
        'http_errors' => false,
    ]));
}

it('performs get and returns psr-7 response', function () {
    $history = [];
    $client = makeClient([new Response(200, [], '{"id":1}')], $history);

    $response = $client->get('users/1');

    expect((string) $response->getBody())->toBe('{"id":1}')
        ->and((string) $history[0]['request']->getUri())->toBe('https://api.test/users/1');
});

it('sends query parameters', function () {
    $history = [];
    makeClient([new Response(200, [], '[]')], $history)->get('users', ['page' => 2]);

    expect((string) $history[0]['request']->getUri())->toBe('https://api.test/users?page=2');
});

it('posts json body', function () {
    $history = [];
    makeClient([new Response(201, [], '{}')], $history)->post('users', ['name' => 'Tim']);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getBody())->toBe('{"name":"Tim"}')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');
});

it('puts json body', function () {
    $history = [];
    makeClient([new Response(200, [], '{}')], $history)->put('users/1', ['name' => 'Tim']);

    expect($history[0]['request']->getMethod())->toBe('PUT');
});

it('sends delete', function () {
    $history = [];
    makeClient([new Response(204, [], '')], $history)->delete('users/1');

    expect($history[0]['request']->getMethod())->toBe('DELETE');
});

it('throws mapped exceptions on error statuses', function () {
    expect(fn () => makeClient([new Response(404, [], '{}')])->get('users/9'))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => makeClient([new Response(422, [], '{"errors":{"a":["b"]}}')])->post('users'))
        ->toThrow(ValidationException::class);
    expect(fn () => makeClient([new Response(500, [], 'oops')])->get('users'))
        ->toThrow(ServerException::class);
});

it('tolerates non-json error bodies', function () {
    try {
        makeClient([new Response(500, [], '<html>')])->get('users');
        $this->fail('Expected exception');
    } catch (ServerException $e) {
        expect($e->body)->toBe([]);
    }
});

it('getMany preserves input order and keys', function () {
    $client = makeClient([
        new Response(200, [], '{"id":"first"}'),
        new Response(200, [], '{"id":"second"}'),
    ]);

    $responses = $client->getMany(['users/1', 'users/2']);

    expect(array_keys($responses))->toBe([0, 1])
        ->and((string) $responses[0]->getBody())->toBe('{"id":"first"}')
        ->and((string) $responses[1]->getBody())->toBe('{"id":"second"}');
});

it('getMany throws when any response is an error', function () {
    makeClient([new Response(200, [], '{}'), new Response(404, [], '{}')])
        ->getMany(['users/1', 'users/2']);
})->throws(ModelNotFoundException::class);

it('builds itself from config', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['headers' => ['X-Token' => 'abc']],
    ]);

    expect($client)->toBeInstanceOf(GuzzleClient::class);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Contracts/ClientInterface.php` — as in the Interfaces block above (with `declare(strict_types=1)`, docblocks for array params).

`src/Clients/GuzzleClient.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RequestException;

final class GuzzleClient implements ClientInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array{base_uri?: string, options?: array<string, mixed>} $config
     */
    public static function fromConfig(array $config): self
    {
        $options = array_merge($config['options'] ?? [], [
            'base_uri' => $config['base_uri'] ?? null,
            'http_errors' => false,
        ]);

        return new self(new Client($options));
    }

    /**
     * @param array<string, mixed> $query
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->get($uri, ['query' => $query]));
    }

    /**
     * @param array<int|string, string> $uris
     * @return array<int|string, ResponseInterface>
     */
    public function getMany(array $uris): array
    {
        $responses = [];

        $requests = function () use ($uris) {
            foreach ($uris as $key => $uri) {
                yield $key => fn () => $this->client->getAsync($uri);
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function (ResponseInterface $response, int|string $key) use (&$responses) {
                $responses[$key] = $response;
            },
        ]);

        $pool->promise()->wait();

        $ordered = [];

        foreach (array_keys($uris) as $key) {
            $ordered[$key] = $this->ensureSuccessful($uris[$key], $responses[$key]);
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function post(string $uri, array $data = []): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->post($uri, ['json' => $data]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function put(string $uri, array $data = []): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->put($uri, ['json' => $data]));
    }

    public function delete(string $uri): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->delete($uri));
    }

    private function ensureSuccessful(string $uri, ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $body = json_decode((string) $response->getBody(), true);

            throw RequestException::fromStatus($uri, $status, is_array($body) ? $body : []);
        }

        return $response;
    }
}
```

Note: `http_errors => false` is forced in `fromConfig`; the test constructor also passes it explicitly. Rejected pool entries (network errors) surface from `wait()` as Guzzle exceptions — acceptable; mapping transport errors is out of scope here.

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: stateless guzzle client with typed errors and ordered getMany"`

---

### Task 6: ClientFactory, ClientManager, ClientResolver

**Files:**
- Create: `src/Contracts/ClientResolverInterface.php`, `src/Clients/ClientFactory.php`, `src/ClientManager.php`, `src/ClientResolver.php`
- Test: `tests/Unit/ClientManagerTest.php`

**Interfaces:**
- Produces:
```php
interface ClientResolverInterface
{
    public function client(?string $name = null, array $options = []): ClientInterface;
}
```
- `ClientManager::__construct(Illuminate\Contracts\Config\Repository $config, ClientFactory $factory)`; reads `rest.default` and `rest.clients.{name}`; caches instances by `name + hash(options)`; `extend(string $name, callable $resolver): void` (by client name or provider name); `getDefaultClient(): string`, `setDefaultClient(string): void`.
- `ClientFactory::createClient(array $config): ClientInterface` — `'guzzle'` → `GuzzleClient::fromConfig($config)`, unknown provider → `InvalidArgumentException`.
- `ClientResolver` — standalone (no config) resolver: `addClient(string, ClientInterface)`, `hasClient(string): bool`, `setDefaultClient(string)`.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Sanchescom\Rest\ClientManager;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Contracts\ClientInterface;

function makeManager(array $clients = [], string $default = 'main'): ClientManager
{
    $config = new Repository(['rest' => ['default' => $default, 'clients' => $clients]]);

    return new ClientManager($config, new ClientFactory());
}

$mainConfig = ['provider' => 'guzzle', 'base_uri' => 'https://api.test/'];

it('resolves the default client from config', function () use ($mainConfig) {
    expect(makeManager(['main' => $mainConfig])->client())->toBeInstanceOf(GuzzleClient::class);
});

it('caches clients per name', function () use ($mainConfig) {
    $manager = makeManager(['main' => $mainConfig]);
    expect($manager->client('main'))->toBe($manager->client('main'));
});

it('does not share cache across different options', function () use ($mainConfig) {
    $manager = makeManager(['main' => $mainConfig]);
    expect($manager->client('main', ['headers' => ['X-A' => '1']]))
        ->not->toBe($manager->client('main'));
});

it('throws for unconfigured client', function () {
    makeManager()->client('missing');
})->throws(InvalidArgumentException::class, 'Client [missing] not configured.');

it('throws for unsupported provider', function () {
    makeManager(['main' => ['provider' => 'soap']])->client('main');
})->throws(InvalidArgumentException::class, 'Unsupported provider [soap]');

it('supports extensions by client name and provider name', function () use ($mainConfig) {
    $fake = Mockery::mock(ClientInterface::class);

    $manager = makeManager(['main' => $mainConfig]);
    $manager->extend('main', fn () => $fake);
    expect($manager->client('main'))->toBe($fake);

    $manager2 = makeManager(['other' => ['provider' => 'custom']], 'other');
    $manager2->extend('custom', fn () => $fake);
    expect($manager2->client('other'))->toBe($fake);
});

it('changes the default client', function () use ($mainConfig) {
    $manager = makeManager(['a' => $mainConfig, 'b' => $mainConfig], 'a');
    $manager->setDefaultClient('b');
    expect($manager->getDefaultClient())->toBe('b');
});

it('standalone resolver stores and resolves clients', function () {
    $fake = Mockery::mock(ClientInterface::class);
    $resolver = new ClientResolver(['main' => $fake]);
    $resolver->setDefaultClient('main');

    expect($resolver->client())->toBe($fake)
        ->and($resolver->hasClient('main'))->toBeTrue()
        ->and($resolver->hasClient('nope'))->toBeFalse();
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Clients/ClientFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use InvalidArgumentException;
use Sanchescom\Rest\Contracts\ClientInterface;

class ClientFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function createClient(array $config): ClientInterface
    {
        $provider = $config['provider'] ?? null;

        return match ($provider) {
            'guzzle' => GuzzleClient::fromConfig($config),
            default => throw new InvalidArgumentException("Unsupported provider [{$provider}]."),
        };
    }
}
```

`src/ClientManager.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

class ClientManager implements ClientResolverInterface
{
    /** @var array<string, ClientInterface> */
    protected array $clients = [];

    /** @var array<string, callable> */
    protected array $extensions = [];

    public function __construct(
        protected Repository $config,
        protected ClientFactory $factory,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function client(?string $name = null, array $options = []): ClientInterface
    {
        $name ??= $this->getDefaultClient();
        $key = $name.':'.md5(serialize($options));

        return $this->clients[$key] ??= $this->makeClient($name, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function makeClient(string $name, array $options = []): ClientInterface
    {
        $config = $this->configuration($name);
        $config['options'] = array_replace_recursive($config['options'] ?? [], $options);

        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $provider = $config['provider'] ?? '';

        if (isset($this->extensions[$provider])) {
            return call_user_func($this->extensions[$provider], $config, $name);
        }

        return $this->factory->createClient($config);
    }

    /**
     * @return array<string, mixed>
     */
    protected function configuration(string $name): array
    {
        $config = $this->config->get("rest.clients.{$name}");

        if ($config === null) {
            throw new InvalidArgumentException("Client [{$name}] not configured.");
        }

        return $config;
    }

    public function getDefaultClient(): string
    {
        return (string) $this->config->get('rest.default');
    }

    public function setDefaultClient(string $name): void
    {
        $this->config->set('rest.default', $name);
    }

    public function extend(string $name, callable $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }
}
```

`src/Contracts/ClientResolverInterface.php` — as in Interfaces block. `src/ClientResolver.php` — port of the old class with strict types: constructor takes `array<string, ClientInterface>`, `client()` throws `InvalidArgumentException` if the name (or default) is unknown.

Add `"mockery/mockery": "^1.6"` to require-dev in this task (`composer require --dev mockery/mockery`).

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: options-aware client manager and factory"`

---

### Task 7: Builder + Model REST behavior

**Files:**
- Create: `src/Builder.php`, `src/Collection.php` (class shell extending `Illuminate\Support\Collection`, paginate comes in Task 8)
- Modify: `src/Model.php` (add REST half)
- Test: `tests/Unit/BuilderTest.php`

**Interfaces:**
- Consumes: `ClientInterface` (Task 5), `ClientResolverInterface` (Task 6), `Json` (Task 3), exceptions (Task 2).
- Produces on `Model`: static `setClientResolver(ClientResolverInterface): void`, `getClient(): ClientInterface`, `getEndpoint(): string` (defaults to snake plural of class basename), `getDataKey(): ?string`, `newCollection(array): Collection`, `newBuilder(): Builder`; magic static/instance `get($id = null)`, `getMany(array $ids)`, `post(array $data)`, `put($id = null, array $data = [])`, `delete($id = null)` forwarded to `Builder`. Protected props for subclasses: `$client` (name), `$endpoint`, `$dataKey`, `$options`.
- Produces on `Builder`: `__construct(Model $model)`, `get(string|int|null $id = null): Model|Collection`, `getMany(array $ids): Collection`, `post(array $data = []): Model`, `put(string|int|null $id = null, array $data = []): Model`, `delete(string|int|null $id = null): bool`.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;

class UserModel extends Model
{
    protected ?string $dataKey = 'data';
}

class PlainItem extends Model
{
    protected ?string $endpoint = 'custom/items';

    protected ?string $dataKey = null;
}

function fakeResolver(ClientInterface $client): void
{
    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    Model::setClientResolver($resolver);
}

function psr(string $json, int $status = 200): Response
{
    return new Response($status, [], $json);
}

it('infers endpoint from class name', function () {
    expect((new UserModel())->getEndpoint())->toBe('user_models')
        ->and((new PlainItem())->getEndpoint())->toBe('custom/items');
});

it('gets a collection and unwraps dataKey', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('user_models', [])->once()
        ->andReturn(psr('{"data":[{"id":1},{"id":2}]}'));
    fakeResolver($client);

    $users = UserModel::get();

    expect($users)->toBeInstanceOf(Collection::class)->toHaveCount(2)
        ->and($users->first())->toBeInstanceOf(UserModel::class)
        ->and($users->first()->id)->toBe(1);
});

it('gets a single model by id', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('user_models/1', [])->once()
        ->andReturn(psr('{"data":{"id":1,"name":"Tim"}}'));
    fakeResolver($client);

    $user = UserModel::get(1);

    expect($user)->toBeInstanceOf(UserModel::class)->and($user->name)->toBe('Tim');
});

it('works without a dataKey', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('custom/items', [])->once()
        ->andReturn(psr('[{"id":1}]'));
    fakeResolver($client);

    expect(PlainItem::get())->toHaveCount(1);
});

it('returns empty collection when dataKey missing in response', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->once()->andReturn(psr('{"unexpected":true}'));
    fakeResolver($client);

    expect(UserModel::get())->toBeInstanceOf(Collection::class)->toHaveCount(0);
});

it('getMany hydrates in input order', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('getMany')->with(['user_models/1', 'user_models/2'])->once()
        ->andReturn([psr('{"data":{"id":1}}'), psr('{"data":{"id":2}}')]);
    fakeResolver($client);

    $users = UserModel::getMany([1, null, 2, '']);

    expect($users->pluck('id')->all())->toBe([1, 2]);
});

it('posts attributes and hydrates the response', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('post')->with('user_models', ['name' => 'Tim'])->once()
        ->andReturn(psr('{"data":{"id":5,"name":"Tim"}}'));
    fakeResolver($client);

    expect(UserModel::post(['name' => 'Tim'])->id)->toBe(5);
});

it('puts by explicit id', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('put')->with('user_models/2', ['email' => 'a@b.c'])->once()
        ->andReturn(psr('{"data":{"id":2,"email":"a@b.c"}}'));
    fakeResolver($client);

    expect(UserModel::put(2, ['email' => 'a@b.c'])->email)->toBe('a@b.c');
});

it('puts an instance using its own key', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('put')->with('user_models/2', ['id' => 2, 'email' => 'new@b.c'])->once()
        ->andReturn(psr('{"data":{"id":2}}'));
    fakeResolver($client);

    $user = new UserModel(['id' => 2]);
    $user->email = 'new@b.c';
    $user->put();
});

it('refuses to put without id or key', function () {
    fakeResolver(Mockery::mock(ClientInterface::class));
    UserModel::put();
})->throws(RestException::class);

it('deletes by id and by instance key', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('delete')->with('user_models/1')->twice()->andReturn(psr('', 204));
    fakeResolver($client);

    expect(UserModel::delete(1))->toBeTrue()
        ->and((new UserModel(['id' => 1]))->delete())->toBeTrue();
});

it('refuses to delete without id or key', function () {
    fakeResolver(Mockery::mock(ClientInterface::class));
    UserModel::delete();
})->throws(RestException::class);
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Collection.php` (shell for now):
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\Collection as BaseCollection;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends BaseCollection<TKey, TValue>
 */
class Collection extends BaseCollection
{
}
```

`src/Builder.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Support\Json;

final class Builder
{
    public function __construct(private readonly Model $model)
    {
    }

    public function get(string|int|null $id = null): Model|Collection
    {
        $payload = $this->decode($this->client()->get($this->uri($id)));

        if ($id !== null) {
            return $this->model->newInstance($this->extract($payload));
        }

        return $this->hydrate($this->extract($payload));
    }

    /**
     * @param array<int, string|int|null> $ids
     */
    public function getMany(array $ids): Collection
    {
        $ids = array_values(array_filter($ids, fn ($id) => $id !== null && $id !== ''));
        $uris = array_map(fn ($id) => $this->uri($id), $ids);

        $items = array_map(
            fn (ResponseInterface $response) => $this->extract($this->decode($response)),
            $this->client()->getMany($uris),
        );

        return $this->hydrate($items);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function post(array $data = []): Model
    {
        $attributes = $this->model->fill($data)->getAttributes();

        $payload = $this->decode($this->client()->post($this->uri(), $attributes));

        return $this->model->newInstance($this->extract($payload));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function put(string|int|null $id = null, array $data = []): Model
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot update a model without an id or primary key value.');
        }

        $attributes = $this->model->fill($data)->getAttributes();

        $payload = $this->decode($this->client()->put($this->uri($id), $attributes));

        return $this->model->newInstance($this->extract($payload));
    }

    public function delete(string|int|null $id = null): bool
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot delete a model without an id or primary key value.');
        }

        $this->client()->delete($this->uri($id));

        return true;
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        return $body === '' ? [] : Json::decode($body);
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    private function extract(array $payload): array
    {
        $key = $this->model->getDataKey();

        if ($key === null) {
            return $payload;
        }

        return (array) Arr::get($payload, $key, []);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function hydrate(array $items): Collection
    {
        return $this->model->newCollection(
            array_map(fn (array $item) => $this->model->newInstance($item), array_values($items)),
        );
    }

    private function uri(string|int|null $id = null): string
    {
        $endpoint = $this->model->getEndpoint();

        return $id === null ? $endpoint : "{$endpoint}/{$id}";
    }

    private function client(): ClientInterface
    {
        return $this->model->getClient();
    }
}
```

`src/Model.php` — add to the class (after the attribute layer):
```php
use Illuminate\Support\Str;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

// --- properties ---

protected static ClientResolverInterface $resolver;

protected ?string $client = null;

protected ?string $endpoint = null;

protected ?string $dataKey = null;

/** @var array<string, mixed> */
protected array $options = [];

// --- methods ---

public static function setClientResolver(ClientResolverInterface $resolver): void
{
    static::$resolver = $resolver;
}

public function getClient(): ClientInterface
{
    return static::$resolver->client($this->client, $this->options);
}

public function getEndpoint(): string
{
    return $this->endpoint ?? Str::snake(Str::pluralStudly(class_basename($this)));
}

public function getDataKey(): ?string
{
    return $this->dataKey;
}

public function newBuilder(): Builder
{
    return new Builder($this);
}

/**
 * @param array<int, static> $models
 * @return Collection<int, static>
 */
public function newCollection(array $models = []): Collection
{
    return new Collection($models);
}

/**
 * @param array<int, mixed> $parameters
 */
public function __call(string $method, array $parameters): mixed
{
    return $this->newBuilder()->{$method}(...$parameters);
}

/**
 * @param array<int, mixed> $parameters
 */
public static function __callStatic(string $method, array $parameters): mixed
{
    return (new static())->{$method}(...$parameters);
}
```

Add class-level PHPDoc to `Model` for the magic API:
```php
/**
 * @method static Model|Collection get(string|int|null $id = null)
 * @method static Collection getMany(array $ids)
 * @method static Model post(array $data = [])
 * @method static Model put(string|int|null $id = null, array $data = [])
 * @method static bool delete(string|int|null $id = null)
 */
```

Note: `Builder` methods are called only through `__call`, so `$user->put()` (instance) and `UserModel::put(...)` (static via fresh instance) both route through the same code; instance calls carry the instance's attributes and key.

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: builder with per-request uris, dataKey handling, key-aware put/delete"`

---

### Task 8: Collection::paginate

**Files:**
- Modify: `src/Collection.php`
- Test: `tests/Unit/CollectionTest.php`

**Interfaces:**
- Produces: `paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator`.

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Sanchescom\Rest\Collection;

beforeEach(function () {
    Container::setInstance(new Container());
});

afterEach(function () {
    Container::setInstance(null);
});

it('paginates the collection in memory', function () {
    $paginator = (new Collection(range(1, 45)))->paginate(10, 'page', 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(45)
        ->and($paginator->currentPage())->toBe(2)
        ->and($paginator->items())->toBe(range(11, 20));
});

it('paginates an empty collection', function () {
    $paginator = (new Collection())->paginate(10, 'page', 1);

    expect($paginator->total())->toBe(0)->and($paginator->items())->toBe([]);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement** (add to `Collection`)

```php
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
{
    $page = $page ?: Paginator::resolveCurrentPage($pageName);

    $total = $this->count();

    $results = $total ? $this->forPage($page, $perPage)->values() : new static();

    return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
        'items' => $results,
        'total' => $total,
        'perPage' => $perPage,
        'currentPage' => $page,
        'options' => [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ],
    ]);
}
```

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: in-memory pagination for rest collections"`

---

### Task 9: ServiceProvider + config publishing + feature test

**Files:**
- Modify: `src/RestServiceProvider.php` (replace placeholder), `config/rest.php`
- Test: `tests/Feature/PackageTest.php`

**Interfaces:**
- Consumes: `ClientManager`, `ClientFactory`, `Model::setClientResolver`.
- Produces: container singleton `rest` + `ClientResolverInterface` alias; `config/rest.php` merged and publishable under tag `rest-config`.

- [ ] **Step 1: Write failing feature tests**

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientManager;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Model;

class FeatureUser extends Model
{
    protected ?string $dataKey = 'data';

    protected ?string $endpoint = 'users';
}

it('registers the rest manager as a singleton', function () {
    expect(app('rest'))->toBeInstanceOf(ClientManager::class)
        ->and(app('rest'))->toBe(app('rest'))
        ->and(app(ClientResolverInterface::class))->toBe(app('rest'));
});

it('merges the default config', function () {
    expect(config('rest.default'))->toBe('localhost')
        ->and(config('rest.clients.localhost.provider'))->toBe('guzzle');
});

it('resolves models end to end through an extended driver', function () {
    config()->set('rest.default', 'testing');
    config()->set('rest.clients.testing', ['provider' => 'mock']);

    app('rest')->extend('mock', function () {
        $mock = new MockHandler([
            new Response(200, [], '{"data":[{"id":1},{"id":2}]}'),
        ]);

        return new GuzzleClient(new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.test/',
            'http_errors' => false,
        ]));
    });

    $users = FeatureUser::get();

    expect($users)->toHaveCount(2)->and($users->first()->id)->toBe(1);
});
```

- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature` — expect FAIL.

- [ ] **Step 3: Implement**

`src/RestServiceProvider.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\ServiceProvider;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

class RestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rest.php', 'rest');

        $this->app->singleton('rest', function ($app) {
            return new ClientManager($app['config'], new ClientFactory());
        });

        $this->app->alias('rest', ClientResolverInterface::class);
        $this->app->alias('rest', ClientManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/rest.php' => config_path('rest.php'),
        ], 'rest-config');

        Model::setClientResolver($this->app->make(ClientResolverInterface::class));
    }
}
```

`config/rest.php` (refresh comments, keep shape):
```php
<?php

declare(strict_types=1);

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
    ],
];
```

- [ ] **Step 4: Run the full suite — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: service provider with config merge, publish tag and container aliases"`

---

### Task 10: Documentation

**Files:**
- Modify: `README.md` (full rewrite), `CONTRIBUTING.md` (fix stale links)
- Create: `CHANGELOG.md`, `UPGRADE.md`

**Interfaces:** none (docs describe the API produced by Tasks 2–9 — verify every snippet against actual signatures).

- [ ] **Step 1: Rewrite README.md** — sections: badges placeholder, install (`composer require sanchescom/laravel-rest`, auto-discovery — no manual provider registration), config (publish command `php artisan vendor:publish --tag=rest-config`, multi-client example with `base_uri` trailing-slash note), model definition (`$endpoint`, `$dataKey`, `$fillable`, `$casts`, `$client`, `$options`), usage (`get`, `get($id)`, `getMany`, `post`, `put($id, $data)`, `$model->put()`, `delete`), error handling (exception table: 404/422/5xx/other), `Collection::paginate`, testing guidance (extend with a mock driver — show the Feature test pattern), Lumen section REMOVED (Lumen is dead), versioning/license (MIT). Fix author links to point at `laravel-rest`, not `php-wifi`.
- [ ] **Step 2: Create CHANGELOG.md** — `## 1.0.0` entry listing: PHP 8.2+/Laravel 11-12, removed jenssegers/model + json-helper, stateless clients, typed exceptions, ordered getMany, options-aware manager cache, config publishing, auto-discovery, full test suite.
- [ ] **Step 3: Create UPGRADE.md** — breaking changes from 0.x: PHP/Laravel floors; `Model` no longer extends `Jenssegers\Model\Model`; clients return PSR-7 responses, not `Illuminate\Http\Response`; `ClientInterface` signature change (uri per call, no `setEndpoint`); 4xx/5xx now throw; `$dataKey`/`$endpoint`/`$fillable` property types now declared (`protected ?string $dataKey = null`); license clarified as MIT.
- [ ] **Step 4: Verify snippets** — for each README code block, confirm the method exists with that signature in `src/` (manual check).
- [ ] **Step 5: Commit** — `git commit -am "docs: rewrite readme, add changelog and upgrade guide"`

---

### Task 11: CI

**Files:**
- Create: `.github/workflows/ci.yml`

**Interfaces:** consumes composer scripts from Task 1.

- [ ] **Step 1: Create workflow**

```yaml
name: CI

on:
  push:
    branches: [master]
  pull_request:

jobs:
  tests:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.2', '8.3', '8.4']
        laravel: ['11.*', '12.*']
    name: PHP ${{ matrix.php }} / Laravel ${{ matrix.laravel }}
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none
      - run: composer require "illuminate/support:${{ matrix.laravel }}" --no-interaction --no-update
      - run: composer update --prefer-dist --no-interaction
      - run: composer lint
      - run: composer analyse
      - run: composer test

```

- [ ] **Step 2: Verify locally** — run `composer lint && composer analyse && composer test` one final time on the full tree; fix anything that fails before committing.
- [ ] **Step 3: Commit** — `git commit -am "chore: github actions ci matrix"`

---

## Self-Review Notes

- Spec coverage: phase 1 items → Tasks 1, 3, 4 (deps/stack), 10 (license/readme), 9 (config publish/auto-discovery); phase 2 bugs → Task 6 (options cache), Tasks 5+7 (endpoint statelessness, response() removal, error handling, put-without-id, hydrate defaults), Task 1 (broken PSR-4); phase 3 → tests in every task + Task 9 (testbench) + Task 11 (CI). Phases 4–5 deferred to a follow-up plan by design.
- Type consistency: `ClientInterface` methods return `Psr\Http\Message\ResponseInterface`; `Builder` consumes them via `decode()`; `Model::getDataKey(): ?string` matches `Builder::extract()`; `ClientResolverInterface::client(?string, array)` matches `Model::getClient()` usage.
- Known judgment calls: transport-level errors (connection refused) propagate as Guzzle exceptions, not wrapped — documented in Task 5; `final_class` disabled in Pint because `Model`, `Collection`, `ClientManager` are extension points.
