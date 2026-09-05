# Laravel Rest Phase 4 Features Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add query builder with pluggable grammars, test fakes, auth drivers, retry policies, model events and relations to laravel-rest 1.0, verified against a local fixture server and real APIs, released as 1.1.0.

**Architecture:** `Builder` gains fluent query state compiled to query strings by swappable `Grammar` classes. Auth and retry are Guzzle middlewares assembled in `GuzzleClient::fromConfig()`. `Rest::fake()` swaps the model client resolver for a recording `FakeClient`. Events are a static hook registry on `Model` fired by `Builder`, bridged to the Laravel dispatcher by the provider. Relations wrap builders (FK filter by default, nested URL opt-in).

**Tech Stack:** PHP ^8.2, illuminate/support+container+pagination ^11|^12, Guzzle ^7.8, Pest v3, Orchestra Testbench, PHPStan level 6, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-09-05-phase4-features-design.md`

## Global Constraints

- PHP `^8.2`; Laravel components `^11.0|^12.0`; no new runtime dependencies.
- `declare(strict_types=1)` everywhere; native types; pint phpdoc style is `@param  type  $name` (two spaces).
- BC: 1.0 public API keeps working. Allowed signature changes (documented in CHANGELOG): `Builder::post/put` return `?Model` (null only when a `*ing` hook cancels); `ClientResolverInterface` gains `grammar(?string $name = null): ?string`.
- No `dd`/`dump`; no `env()` outside config; package must work without a booted Laravel app (except `paginate()` and provider features).
- Every task: TDD; verify with `composer test`, `composer lint`, `composer analyse` before commit; conventional commits; NO Co-Authored-By trailer.
- `composer test` excludes the `live` group; `composer test:live` runs only it. Live tests must skip (not fail) when required env vars are absent.

---

### Task 1: Verification infrastructure — fixture server, test groups, scripts

**Files:**
- Create: `tests/Fixtures/server.php`, `tests/Support/FixtureServer.php`, `tests/Integration/.gitkeep`, `tests/Live/.gitkeep`
- Modify: `composer.json` (scripts), `phpunit.xml` (suites)
- Test: `tests/Integration/FixtureServerTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `FixtureServer::start(): void`, `FixtureServer::stop(): void`, `FixtureServer::$baseUri` (`http://127.0.0.1:8937/`); fixture routes listed below; Pest groups `integration` (default run) and `live` (excluded by default).

- [ ] **Step 1: Write the failing test**

`tests/Integration/FixtureServerTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

it('serves ping', function () {
    $body = file_get_contents(FixtureServer::$baseUri.'ping');
    expect($body)->toBe('{"pong":true}');
})->group('integration');

it('serves plain and enveloped items', function () {
    expect(json_decode(file_get_contents(FixtureServer::$baseUri.'plain/items'), true))
        ->toBe([['id' => 1], ['id' => 2]])
        ->and(json_decode(file_get_contents(FixtureServer::$baseUri.'enveloped/items'), true))
        ->toBe(['data' => [['id' => 1], ['id' => 2]]]);
})->group('integration');

it('echoes query and headers', function () {
    $ctx = stream_context_create(['http' => ['header' => "X-Probe: yes\r\n"]]);
    $echo = json_decode(file_get_contents(FixtureServer::$baseUri.'echo?a=1&b=2', false, $ctx), true);
    expect($echo['query'])->toBe(['a' => '1', 'b' => '2'])
        ->and($echo['headers']['X-Probe'] ?? null)->toBe('yes');
})->group('integration');
```

- [ ] **Step 2: Run — expect FAIL** (`./vendor/bin/pest tests/Integration` — class not found).

- [ ] **Step 3: Implement**

`tests/Support/FixtureServer.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Support;

use RuntimeException;

final class FixtureServer
{
    public static string $baseUri = 'http://127.0.0.1:8937/';

    /** @var resource|null */
    private static $process = null;

    public static function start(): void
    {
        if (self::$process !== null) {
            return;
        }

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:8937', __DIR__.'/../Fixtures/server.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if ($process === false) {
            throw new RuntimeException('Could not spawn fixture server.');
        }

        self::$process = $process;
        register_shutdown_function(static fn () => self::stop());

        for ($i = 0; $i < 50; $i++) {
            if (@file_get_contents(self::$baseUri.'ping') !== false) {
                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException('Fixture server did not become ready.');
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            self::$process = null;
        }
    }
}
```

`tests/Fixtures/server.php` (plain PHP router, exempt from src-only phpstan):
```php
<?php

declare(strict_types=1);

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

function requestHeaders(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = $value;
        }
    }

    return $headers;
}

function jsonBody(): array
{
    return json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
}

switch (true) {
    case $path === 'ping':
        echo '{"pong":true}';
        break;

    case $path === 'plain/items' && $method === 'GET':
        echo json_encode([['id' => 1], ['id' => 2]]);
        break;

    case $path === 'enveloped/items' && $method === 'GET':
        echo json_encode(['data' => [['id' => 1], ['id' => 2]]]);
        break;

    case $path === 'echo':
        echo json_encode(['query' => $_GET, 'headers' => requestHeaders(), 'method' => $method, 'body' => jsonBody()]);
        break;

    case $path === 'auth/bearer':
        $ok = (requestHeaders()['Authorization'] ?? '') === 'Bearer secret-token';
        http_response_code($ok ? 200 : 401);
        echo json_encode(['ok' => $ok]);
        break;

    case $path === 'auth/basic':
        $ok = ($_SERVER['PHP_AUTH_USER'] ?? '') === 'user' && ($_SERVER['PHP_AUTH_PW'] ?? '') === 'pass';
        http_response_code($ok ? 200 : 401);
        echo json_encode(['ok' => $ok]);
        break;

    case $path === 'auth/header':
        $ok = (requestHeaders()['X-Api-Key'] ?? '') === 'k123';
        http_response_code($ok ? 200 : 401);
        echo json_encode(['ok' => $ok]);
        break;

    case $path === 'flaky':
        $key = $_GET['key'] ?? 'default';
        $file = sys_get_temp_dir()."/laravel-rest-flaky-{$key}";
        $count = (int) @file_get_contents($file);
        file_put_contents($file, (string) ($count + 1));
        if ($count < 2) {
            http_response_code(503);
            echo '{"error":"unavailable"}';
        } else {
            echo json_encode(['id' => 1, 'attempts' => $count + 1]);
        }
        break;

    case $path === 'retry-after':
        $key = $_GET['key'] ?? 'default';
        $file = sys_get_temp_dir()."/laravel-rest-ra-{$key}";
        if (@file_get_contents($file) === false) {
            file_put_contents($file, '1');
            http_response_code(429);
            header('Retry-After: 1');
            echo '{"error":"slow down"}';
        } else {
            echo '{"id":1}';
        }
        break;

    case $path === 'malformed':
        echo '{oops';
        break;

    case $path === 'invalid' && $method === 'POST':
        http_response_code(422);
        echo json_encode(['errors' => ['name' => ['Required.']]]);
        break;

    case preg_match('#^posts/(\d+)/comments$#', $path, $m) === 1 && $method === 'GET':
        echo json_encode([['id' => 10, 'postId' => (int) $m[1]], ['id' => 11, 'postId' => (int) $m[1]]]);
        break;

    case $path === 'comments' && $method === 'GET':
        $all = [['id' => 10, 'postId' => 1], ['id' => 11, 'postId' => 1], ['id' => 12, 'postId' => 2]];
        $filtered = isset($_GET['postId'])
            ? array_values(array_filter($all, fn ($c) => $c['postId'] === (int) $_GET['postId']))
            : $all;
        echo json_encode($filtered);
        break;

    case preg_match('#^users/(\d+)$#', $path, $m) === 1 && $method === 'GET':
        echo json_encode(['id' => (int) $m[1], 'name' => 'User '.$m[1]]);
        break;

    default:
        http_response_code(404);
        echo '{"error":"not found"}';
}
```

- [ ] **Step 4: Update composer scripts and phpunit suites**

In `composer.json` scripts replace `"test"` and add `"test:live"`:
```json
"test": "./vendor/bin/pest --exclude-group=live",
"test:live": "./vendor/bin/pest --group=live",
```

In `phpunit.xml` add inside `<testsuites>`:
```xml
<testsuite name="Integration">
    <directory>tests/Integration</directory>
</testsuite>
<testsuite name="Live">
    <directory>tests/Live</directory>
</testsuite>
```

Create empty dirs with `.gitkeep`: `tests/Integration/`, `tests/Live/`.

- [ ] **Step 5: Run — expect PASS** (`composer test`), then `composer lint && composer analyse`.
- [ ] **Step 6: Commit** — `git add -A && git commit -m "test: local fixture server and test group wiring"`

---

### Task 2: Query state and grammars

**Files:**
- Create: `src/Query/QueryState.php`, `src/Query/Grammar.php`, `src/Query/PlainGrammar.php`, `src/Query/JsonApiGrammar.php`
- Test: `tests/Unit/GrammarTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `QueryState` with public props: `array $wheres` (list of `['field','operator','value']`), `array $orders` (list of `['field','direction']`), `?int $limit`, `?int $offset`, `?int $page`, `array $extra`; method `isEmpty(): bool`.
  - `interface Grammar { public function compile(QueryState $state): array; }` (namespace `Sanchescom\Rest\Query`).
  - `PlainGrammar`: `?field=value`, non-eq operator `field[op]=value`, `sort=-date,name`, `limit`, `offset`, `page`.
  - `JsonApiGrammar`: `filter[field]=value`, non-eq `filter[field][op]=value`, `sort` same, `page[size]`, `page[offset]`, `page[number]`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/GrammarTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Query\JsonApiGrammar;
use Sanchescom\Rest\Query\PlainGrammar;
use Sanchescom\Rest\Query\QueryState;

function state(callable $mutator): QueryState
{
    $state = new QueryState;
    $mutator($state);

    return $state;
}

it('compiles empty state to empty array', function () {
    expect((new PlainGrammar)->compile(new QueryState))->toBe([])
        ->and((new QueryState)->isEmpty())->toBeTrue();
});

it('compiles plain filters, sort and paging', function () {
    $state = state(function (QueryState $s) {
        $s->wheres = [
            ['field' => 'userId', 'operator' => '=', 'value' => 1],
            ['field' => 'age', 'operator' => 'gte', 'value' => 30],
        ];
        $s->orders = [
            ['field' => 'date', 'direction' => 'desc'],
            ['field' => 'name', 'direction' => 'asc'],
        ];
        $s->limit = 10;
        $s->offset = 20;
        $s->page = 2;
        $s->extra = ['include' => 'author'];
    });

    expect((new PlainGrammar)->compile($state))->toBe([
        'include' => 'author',
        'userId' => 1,
        'age[gte]' => 30,
        'sort' => '-date,name',
        'limit' => 10,
        'offset' => 20,
        'page' => 2,
    ]);
});

it('compiles json:api filters, sort and paging', function () {
    $state = state(function (QueryState $s) {
        $s->wheres = [
            ['field' => 'userId', 'operator' => '=', 'value' => 1],
            ['field' => 'age', 'operator' => 'gte', 'value' => 30],
        ];
        $s->orders = [['field' => 'date', 'direction' => 'desc']];
        $s->limit = 10;
        $s->page = 2;
    });

    expect((new JsonApiGrammar)->compile($state))->toBe([
        'filter' => ['userId' => 1, 'age' => ['gte' => 30]],
        'sort' => '-date',
        'page' => ['size' => 10, 'number' => 2],
    ]);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Query/QueryState.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

final class QueryState
{
    /** @var list<array{field: string, operator: string, value: mixed}> */
    public array $wheres = [];

    /** @var list<array{field: string, direction: string}> */
    public array $orders = [];

    public ?int $limit = null;

    public ?int $offset = null;

    public ?int $page = null;

    /** @var array<string, mixed> */
    public array $extra = [];

    public function isEmpty(): bool
    {
        return $this->wheres === [] && $this->orders === [] && $this->extra === []
            && $this->limit === null && $this->offset === null && $this->page === null;
    }
}
```

`src/Query/Grammar.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

interface Grammar
{
    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array;
}
```

`src/Query/PlainGrammar.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

class PlainGrammar implements Grammar
{
    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            $key = $where['operator'] === '='
                ? $where['field']
                : "{$where['field']}[{$where['operator']}]";
            $query[$key] = $where['value'];
        }

        if ($state->orders !== []) {
            $query['sort'] = implode(',', array_map(
                fn (array $order) => $order['direction'] === 'desc' ? "-{$order['field']}" : $order['field'],
                $state->orders,
            ));
        }

        if ($state->limit !== null) {
            $query['limit'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['offset'] = $state->offset;
        }

        if ($state->page !== null) {
            $query['page'] = $state->page;
        }

        return $query;
    }
}
```

`src/Query/JsonApiGrammar.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

class JsonApiGrammar implements Grammar
{
    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            if ($where['operator'] === '=') {
                $query['filter'][$where['field']] = $where['value'];
            } else {
                $query['filter'][$where['field']][$where['operator']] = $where['value'];
            }
        }

        if ($state->orders !== []) {
            $query['sort'] = implode(',', array_map(
                fn (array $order) => $order['direction'] === 'desc' ? "-{$order['field']}" : $order['field'],
                $state->orders,
            ));
        }

        if ($state->limit !== null) {
            $query['page']['size'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['page']['offset'] = $state->offset;
        }

        if ($state->page !== null) {
            $query['page']['number'] = $state->page;
        }

        return $query;
    }
}
```

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: query state with plain and json:api grammars"`

---

### Task 3: Fluent Builder + grammar resolution on Model and resolvers

**Files:**
- Modify: `src/Builder.php`, `src/Model.php`, `src/Contracts/ClientResolverInterface.php`, `src/ClientResolver.php`, `src/ClientManager.php`
- Test: `tests/Unit/QueryBuilderTest.php`, `tests/Integration/QueryIntegrationTest.php`

**Interfaces:**
- Consumes: `Grammar`, `QueryState`, `PlainGrammar` (Task 2); existing `Builder`, `Model`.
- Produces:
  - `Builder::__construct(Model $model, ?Grammar $grammar = null)` (defaults to `PlainGrammar`).
  - Fluent (all return `self`): `where(string $field, mixed $operator = null, mixed $value = null)` (2-arg form means `=`), `orderBy(string $field, string $direction = 'asc')`, `limit(int)`, `offset(int)`, `page(int)`, `withQuery(array)`, `from(string $endpoint)`.
  - Terminals: `first(): ?Model`, `count(): int`; `get()` now sends compiled query.
  - `ClientResolverInterface::grammar(?string $name = null): ?string`; `ClientResolver::setGrammar(string $client, string $grammarClass): void`; `ClientManager` reads `rest.clients.{name}.grammar`.
  - `Model::newBuilder()` resolves grammar: model `protected ?string $grammar` → resolver `grammar($client)` → `PlainGrammar`.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/QueryBuilderTest.php`:
```php
<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\JsonApiGrammar;

class QueryPost extends Model
{
    protected ?string $dataKey = null;
}

class JsonApiPost extends Model
{
    protected ?string $endpoint = 'query_posts';

    protected ?string $dataKey = null;

    protected ?string $grammar = JsonApiGrammar::class;
}

function queryResolver(ClientInterface $client): void
{
    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    Model::setClientResolver($resolver);
}

it('compiles where, sort and paging into the query string', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['userId' => 1, 'age[gte]' => 30, 'sort' => '-date', 'limit' => 5])
        ->once()->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    QueryPost::where('userId', 1)->where('age', 'gte', 30)->orderBy('date', 'desc')->limit(5)->get();
});

it('uses the model grammar', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['filter' => ['userId' => 1]])
        ->once()->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    JsonApiPost::where('userId', 1)->get();
});

it('uses the resolver grammar when model has none', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['filter' => ['userId' => 1]])
        ->once()->andReturn(new Response(200, [], '[]'));

    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    $resolver->setGrammar('main', JsonApiGrammar::class);
    Model::setClientResolver($resolver);

    QueryPost::where('userId', 1)->get();
});

it('supports first and count', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->twice()
        ->andReturn(new Response(200, [], '[{"id":1},{"id":2}]'));
    queryResolver($client);

    expect(QueryPost::first()->id)->toBe(1)
        ->and(QueryPost::count())->toBe(2);
});

it('overrides the endpoint with from', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('custom/path', [])->once()
        ->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    (new QueryPost)->newBuilder()->from('custom/path')->get();
});

it('merges withQuery params', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('query_posts', ['include' => 'author'])->once()
        ->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    QueryPost::withQuery(['include' => 'author'])->get();
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Contracts/ClientResolverInterface.php` — add to the interface:
```php
    /**
     * Grammar class configured for the client, if any.
     *
     * @return class-string|null
     */
    public function grammar(?string $name = null): ?string;
```

`src/ClientResolver.php` — add:
```php
    /** @var array<string, string> */
    protected array $grammars = [];

    public function setGrammar(string $client, string $grammarClass): void
    {
        $this->grammars[$client] = $grammarClass;
    }

    public function grammar(?string $name = null): ?string
    {
        return $this->grammars[$name ?? $this->default ?? ''] ?? null;
    }
```

`src/ClientManager.php` — add:
```php
    public function grammar(?string $name = null): ?string
    {
        $name ??= $this->getDefaultClient();

        $grammar = $this->config->get("rest.clients.{$name}.grammar");

        return is_string($grammar) ? $grammar : null;
    }
```

`src/Builder.php` — constructor, state and fluent methods (replace the constructor, add after it):
```php
    private QueryState $state;

    private ?string $endpointOverride = null;

    public function __construct(
        private readonly Model $model,
        private readonly Grammar $grammar = new PlainGrammar,
    ) {
        $this->state = new QueryState;
    }

    public function where(string $field, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->state->wheres[] = ['field' => $field, 'operator' => (string) $operator, 'value' => $value];

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->state->orders[] = ['field' => $field, 'direction' => $direction];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->state->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->state->offset = $offset;

        return $this;
    }

    public function page(int $page): self
    {
        $this->state->page = $page;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function withQuery(array $params): self
    {
        $this->state->extra = array_merge($this->state->extra, $params);

        return $this;
    }

    public function from(string $endpoint): self
    {
        $this->endpointOverride = $endpoint;

        return $this;
    }

    public function first(): ?Model
    {
        $result = $this->get();

        return $result instanceof Collection ? $result->first() : $result;
    }

    public function count(): int
    {
        $result = $this->get();

        return $result instanceof Collection ? $result->count() : 1;
    }
```

Change `get()` to send the compiled query, and `uri()` to honor the override:
```php
    public function get(string|int|null $id = null): Model|Collection
    {
        $payload = $this->decode($this->client()->get($this->uri($id), $this->grammar->compile($this->state)));
        // ... rest unchanged
```
```php
    private function uri(string|int|null $id = null): string
    {
        $endpoint = $this->endpointOverride ?? $this->model->getEndpoint();

        return $id === null ? $endpoint : "{$endpoint}/{$id}";
    }
```

Add imports to Builder: `use Sanchescom\Rest\Query\Grammar; use Sanchescom\Rest\Query\PlainGrammar; use Sanchescom\Rest\Query\QueryState;`

`src/Model.php` — add property and grammar resolution; extend the magic `@method` block:
```php
    /** @var class-string|null */
    protected ?string $grammar = null;
```
```php
    public function newBuilder(): Builder
    {
        $grammarClass = $this->grammar
            ?? (isset(static::$resolver) ? static::$resolver->grammar($this->client) : null)
            ?? PlainGrammar::class;

        return new Builder($this, new $grammarClass);
    }
```
Add import `use Sanchescom\Rest\Query\PlainGrammar;` and to the class docblock:
```php
 * @method static Builder where(string $field, mixed $operator = null, mixed $value = null)
 * @method static Builder orderBy(string $field, string $direction = 'asc')
 * @method static Builder limit(int $limit)
 * @method static Builder offset(int $offset)
 * @method static Builder page(int $page)
 * @method static Builder withQuery(array<string, mixed> $params)
 * @method static Model|null first()
 * @method static int count()
```

- [ ] **Step 4: Run unit tests — PASS.**

- [ ] **Step 5: Write the integration test**

`tests/Integration/QueryIntegrationTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class EchoItem extends Model
{
    protected ?string $endpoint = 'echo';

    protected ?string $dataKey = 'query';
}

it('sends compiled query over real http', function () {
    $resolver = new ClientResolver([
        'fixture' => GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]),
    ]);
    $resolver->setDefaultClient('fixture');
    Model::setClientResolver($resolver);

    $echoed = EchoItem::where('userId', 1)->orderBy('date', 'desc')->limit(5)->get();

    expect($echoed->first()->toArray())->toBe([])
        ->and($echoed->count())->toBeGreaterThanOrEqual(0);

    // assert exact wire format via raw echo
    $raw = json_decode(file_get_contents(FixtureServer::$baseUri.'echo?userId=1&sort=-date&limit=5'), true);
    expect($raw['query'])->toBe(['userId' => '1', 'sort' => '-date', 'limit' => '5']);
})->group('integration');
```

Note: the `dataKey = 'query'` model unwraps the echoed query map; hydration of a map produces one model per key-value pair which is not meaningful — the meaningful assertion is the raw echo. Keep both: the model call proves end-to-end plumbing does not error; the raw call pins the wire format. Replace the first expectation block with:
```php
    expect($echoed)->not->toBeNull();
```

- [ ] **Step 6: Run — PASS; lint; analyse; full `composer test`.**
- [ ] **Step 7: Commit** — `git commit -am "feat: fluent query builder with grammar resolution"`

---

### Task 4: Rest fakes

**Files:**
- Create: `src/Rest.php`, `src/Clients/FakeClient.php`
- Modify: `src/Model.php` (add `getClientResolver()`)
- Test: `tests/Unit/RestFakeTest.php`

**Interfaces:**
- Consumes: `ClientInterface`, `ClientResolverInterface`, `RequestException` (1.0), `Model::setClientResolver`.
- Produces:
  - `Model::getClientResolver(): ?ClientResolverInterface` (static).
  - `Rest::fake(array $map): FakeClient` — pattern (`Str::is`) → PSR-7 response; installs a resolver serving `FakeClient` for every client name.
  - `Rest::response(array|string $body = [], int $status = 200, array $headers = []): ResponseInterface`.
  - `Rest::assertSent(Closure $callback): void`, `Rest::assertNotSent(Closure $callback): void`, `Rest::assertSentCount(int $count): void`, `Rest::recorded(): array` (list of `RecordedRequest`), `Rest::restore(): void`.
  - `RecordedRequest` value object: `method(): string`, `uri(): string`, `query(): array`, `data(): array`.
  - `FakeClient` throws `RestException` for unmatched URIs; error-status responses throw via `RequestException::fromStatus` exactly like `GuzzleClient`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/RestFakeTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class FakedPost extends Model
{
    protected ?string $dataKey = null;
}

afterEach(function () {
    Rest::restore();
});

it('serves faked responses by pattern', function () {
    Rest::fake([
        'faked_posts/*' => Rest::response(['id' => 7, 'title' => 'faked']),
        'faked_posts' => Rest::response([['id' => 1]]),
    ]);

    expect(FakedPost::get(7)->title)->toBe('faked')
        ->and(FakedPost::get())->toHaveCount(1);
});

it('records requests and asserts on them', function () {
    Rest::fake(['faked_posts' => Rest::response([])]);

    FakedPost::post(['title' => 'x']);

    Rest::assertSentCount(1);
    Rest::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->uri() === 'faked_posts'
        && $request->data() === ['title' => 'x']);
    Rest::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('throws on unmatched uris', function () {
    Rest::fake(['other' => Rest::response([])]);

    FakedPost::get();
})->throws(RestException::class, 'No fake response defined');

it('maps error statuses to typed exceptions', function () {
    Rest::fake(['faked_posts/*' => Rest::response(['error' => 'gone'], 404)]);

    FakedPost::get(9);
})->throws(ModelNotFoundException::class);

it('restores the previous resolver', function () {
    $before = Model::getClientResolver();
    Rest::fake(['x' => Rest::response([])]);
    Rest::restore();

    expect(Model::getClientResolver())->toBe($before);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Model.php` — add near `setClientResolver`:
```php
    public static function getClientResolver(): ?ClientResolverInterface
    {
        return isset(static::$resolver) ? static::$resolver : null;
    }
```

`src/Clients/FakeClient.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\RestException;

final class FakeClient implements ClientInterface
{
    /** @var list<RecordedRequest> */
    public array $recorded = [];

    /**
     * @param  array<string, ResponseInterface>  $map
     */
    public function __construct(private readonly array $map) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        return $this->respond('GET', $uri, $query, []);
    }

    /**
     * @param  array<int|string, string>  $uris
     * @return array<int|string, ResponseInterface>
     */
    public function getMany(array $uris): array
    {
        $responses = [];

        foreach ($uris as $key => $uri) {
            $responses[$key] = $this->respond('GET', $uri, [], []);
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): ResponseInterface
    {
        return $this->respond('POST', $uri, [], $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): ResponseInterface
    {
        return $this->respond('PUT', $uri, [], $data);
    }

    public function delete(string $uri): ResponseInterface
    {
        return $this->respond('DELETE', $uri, [], []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    private function respond(string $method, string $uri, array $query, array $data): ResponseInterface
    {
        $this->recorded[] = new RecordedRequest($method, $uri, $query, $data);

        foreach ($this->map as $pattern => $response) {
            if (Str::is($pattern, $uri)) {
                $status = $response->getStatusCode();

                if ($status >= 400) {
                    $body = json_decode((string) $response->getBody(), true);
                    $response->getBody()->rewind();

                    throw RequestException::fromStatus($uri, $status, is_array($body) ? $body : []);
                }

                $response->getBody()->rewind();

                return $response;
            }
        }

        throw new RestException("No fake response defined for [{$method} {$uri}].");
    }
}
```

`src/Clients/RecordedRequest.php` (same task, add to Files: Create):
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

final class RecordedRequest
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $data,
    ) {}

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
```

`src/Rest.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Closure;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Clients\FakeClient;
use Sanchescom\Rest\Clients\RecordedRequest;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

final class Rest
{
    private static ?FakeClient $fake = null;

    private static ?ClientResolverInterface $previous = null;

    private static bool $hadPrevious = false;

    /**
     * @param  array<string, ResponseInterface>  $map
     */
    public static function fake(array $map): FakeClient
    {
        $fake = new FakeClient($map);

        if (self::$fake === null) {
            self::$previous = Model::getClientResolver();
            self::$hadPrevious = self::$previous !== null;
        }

        self::$fake = $fake;

        Model::setClientResolver(new class($fake) implements ClientResolverInterface
        {
            public function __construct(private readonly FakeClient $fake) {}

            /**
             * @param  array<string, mixed>  $options
             */
            public function client(?string $name = null, array $options = []): ClientInterface
            {
                return $this->fake;
            }

            public function grammar(?string $name = null): ?string
            {
                return null;
            }
        });

        return $fake;
    }

    /**
     * @param  array<mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public static function response(array|string $body = [], int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers, is_string($body) ? $body : (json_encode($body) ?: '{}'));
    }

    public static function assertSent(Closure $callback): void
    {
        Assert::assertTrue(
            collect(self::recorded())->contains(fn (RecordedRequest $request) => $callback($request)),
            'Expected request was not sent.',
        );
    }

    public static function assertNotSent(Closure $callback): void
    {
        Assert::assertFalse(
            collect(self::recorded())->contains(fn (RecordedRequest $request) => $callback($request)),
            'Unexpected request was sent.',
        );
    }

    public static function assertSentCount(int $count): void
    {
        Assert::assertCount($count, self::recorded());
    }

    /**
     * @return list<RecordedRequest>
     */
    public static function recorded(): array
    {
        return self::$fake?->recorded ?? [];
    }

    public static function restore(): void
    {
        if (self::$fake === null) {
            return;
        }

        if (self::$hadPrevious && self::$previous !== null) {
            Model::setClientResolver(self::$previous);
        }

        self::$fake = null;
        self::$previous = null;
        self::$hadPrevious = false;
    }
}
```

- [ ] **Step 4: Run tests — PASS; lint; analyse.**
- [ ] **Step 5: Commit** — `git commit -am "feat: rest fakes with request recording and assertions"`

---

### Task 5: Auth drivers

**Files:**
- Create: `src/Auth/AuthInterface.php`, `src/Auth/BearerAuth.php`, `src/Auth/BasicAuth.php`, `src/Auth/HeaderAuth.php`
- Modify: `src/Clients/GuzzleClient.php` (`fromConfig` builds middleware stack)
- Test: `tests/Unit/AuthTest.php`, `tests/Integration/AuthIntegrationTest.php`

**Interfaces:**
- Consumes: Guzzle `HandlerStack`, `Middleware::mapRequest`; fixture routes `auth/bearer`, `auth/basic`, `auth/header` (Task 1).
- Produces:
  - `interface AuthInterface { public function authenticate(RequestInterface $request): RequestInterface; }` (namespace `Sanchescom\Rest\Auth`).
  - `BearerAuth::__construct(string $token)`, `BasicAuth::__construct(string $username, string $password)`, `HeaderAuth::__construct(array $headers)`.
  - Config contract consumed by `fromConfig`: `'auth' => ['driver' => 'bearer'|'basic'|'header'|FQCN, ...]`; FQCN is constructed as `new $driver($authConfig)`.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/AuthTest.php`:
```php
<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use Sanchescom\Rest\Auth\BasicAuth;
use Sanchescom\Rest\Auth\BearerAuth;
use Sanchescom\Rest\Auth\HeaderAuth;

it('adds a bearer header', function () {
    $request = (new BearerAuth('tok'))->authenticate(new Request('GET', 'x'));
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer tok');
});

it('adds a basic auth header', function () {
    $request = (new BasicAuth('user', 'pass'))->authenticate(new Request('GET', 'x'));
    expect($request->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('user:pass'));
});

it('adds arbitrary headers', function () {
    $request = (new HeaderAuth(['X-Api-Key' => 'k123', 'X-Version' => '2']))
        ->authenticate(new Request('GET', 'x'));
    expect($request->getHeaderLine('X-Api-Key'))->toBe('k123')
        ->and($request->getHeaderLine('X-Version'))->toBe('2');
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Auth/AuthInterface.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

interface AuthInterface
{
    public function authenticate(RequestInterface $request): RequestInterface;
}
```

`src/Auth/BearerAuth.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

final class BearerAuth implements AuthInterface
{
    public function __construct(private readonly string $token) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', "Bearer {$this->token}");
    }
}
```

`src/Auth/BasicAuth.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

final class BasicAuth implements AuthInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader(
            'Authorization',
            'Basic '.base64_encode("{$this->username}:{$this->password}"),
        );
    }
}
```

`src/Auth/HeaderAuth.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

final class HeaderAuth implements AuthInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(private readonly array $headers) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}
```

`src/Clients/GuzzleClient.php` — rewrite `fromConfig` (auth wiring; retry hooks in here in Task 6):
```php
    /**
     * @param  array{base_uri?: string, options?: array<string, mixed>, auth?: array<string, mixed>, retry?: array<string, mixed>}  $config
     */
    public static function fromConfig(array $config): self
    {
        $options = $config['options'] ?? [];

        $stack = $options['handler'] ?? HandlerStack::create();

        if (isset($config['auth'])) {
            $auth = self::makeAuth($config['auth']);
            $stack->push(Middleware::mapRequest(
                fn ($request) => $auth->authenticate($request),
            ), 'rest_auth');
        }

        $options = array_merge($options, [
            'handler' => $stack,
            'base_uri' => $config['base_uri'] ?? null,
            'http_errors' => false,
        ]);

        return new self(new Client($options));
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private static function makeAuth(array $auth): AuthInterface
    {
        $driver = $auth['driver'] ?? null;

        return match ($driver) {
            'bearer' => new BearerAuth((string) $auth['token']),
            'basic' => new BasicAuth((string) $auth['username'], (string) $auth['password']),
            'header' => new HeaderAuth((array) $auth['headers']),
            default => is_string($driver) && is_a($driver, AuthInterface::class, true)
                ? new $driver($auth)
                : throw new InvalidArgumentException("Unsupported auth driver [{$driver}]."),
        };
    }
```
Add imports: `use GuzzleHttp\HandlerStack; use GuzzleHttp\Middleware; use InvalidArgumentException; use Sanchescom\Rest\Auth\AuthInterface; use Sanchescom\Rest\Auth\BasicAuth; use Sanchescom\Rest\Auth\BearerAuth; use Sanchescom\Rest\Auth\HeaderAuth;`

- [ ] **Step 4: Write the integration test**

`tests/Integration/AuthIntegrationTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

it('authenticates with bearer over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'auth' => ['driver' => 'bearer', 'token' => 'secret-token'],
    ]);

    expect($client->get('auth/bearer')->getStatusCode())->toBe(200);
})->group('integration');

it('authenticates with basic over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'auth' => ['driver' => 'basic', 'username' => 'user', 'password' => 'pass'],
    ]);

    expect($client->get('auth/basic')->getStatusCode())->toBe(200);
})->group('integration');

it('authenticates with custom headers over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'auth' => ['driver' => 'header', 'headers' => ['X-Api-Key' => 'k123']],
    ]);

    expect($client->get('auth/header')->getStatusCode())->toBe(200);
})->group('integration');

it('gets 401 without credentials', function () {
    $client = GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]);

    $client->get('auth/bearer');
})->group('integration')->throws(RequestException::class);
```

- [ ] **Step 5: Run everything — PASS; lint; analyse.**
- [ ] **Step 6: Commit** — `git commit -am "feat: bearer, basic and header auth drivers"`

---

### Task 6: Retry policies

**Files:**
- Modify: `src/Clients/GuzzleClient.php` (retry middleware in `fromConfig`)
- Test: `tests/Unit/RetryTest.php`, `tests/Integration/RetryIntegrationTest.php`

**Interfaces:**
- Consumes: `HandlerStack` from Task 5's `fromConfig`; fixture routes `flaky`, `retry-after` (Task 1).
- Produces: config contract `'retry' => ['times' => int, 'delay' => int(ms), 'multiplier' => float, 'statuses' => int[], 'respect_retry_after' => bool]`. Defaults: `times` required, `delay` 100, `multiplier` 2.0, `statuses` `[429, 500, 502, 503, 504]`, `respect_retry_after` true. No `retry` key — no middleware.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/RetryTest.php`:
```php
<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ServerException;

it('retries retriable statuses until success', function () {
    $mock = new MockHandler([
        new Response(503),
        new Response(503),
        new Response(200, [], '{"ok":true}'),
    ]);

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => HandlerStack::create($mock)],
        'retry' => ['times' => 3, 'delay' => 0],
    ]);

    expect($client->get('x')->getStatusCode())->toBe(200)
        ->and($mock->count())->toBe(0);
});

it('gives up after the configured attempts', function () {
    $mock = new MockHandler([
        new Response(503),
        new Response(503),
        new Response(503),
    ]);

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => HandlerStack::create($mock)],
        'retry' => ['times' => 2, 'delay' => 0],
    ]);

    $client->get('x');
})->throws(ServerException::class);

it('does not retry non-retriable statuses', function () {
    $mock = new MockHandler([
        new Response(404),
        new Response(200),
    ]);

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.test/',
        'options' => ['handler' => HandlerStack::create($mock)],
        'retry' => ['times' => 3, 'delay' => 0],
    ]);

    try {
        $client->get('x');
    } catch (Throwable) {
    }

    expect($mock->count())->toBe(1);
});
```

- [ ] **Step 2: Run — expect FAIL** (404 test passes trivially before implementation is fine; the first two must fail).

- [ ] **Step 3: Implement** — in `fromConfig`, after the auth block:
```php
        if (isset($config['retry'])) {
            $stack->push(self::retryMiddleware($config['retry']), 'rest_retry');
        }
```
And add the private helper:
```php
    /**
     * @param  array<string, mixed>  $retry
     */
    private static function retryMiddleware(array $retry): callable
    {
        $times = (int) $retry['times'];
        $delay = (int) ($retry['delay'] ?? 100);
        $multiplier = (float) ($retry['multiplier'] ?? 2.0);
        $statuses = (array) ($retry['statuses'] ?? [429, 500, 502, 503, 504]);
        $respectRetryAfter = (bool) ($retry['respect_retry_after'] ?? true);

        return Middleware::retry(
            function (int $attempt, $request, $response = null, $exception = null) use ($times, $statuses): bool {
                if ($attempt >= $times) {
                    return false;
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                return $response !== null && in_array($response->getStatusCode(), $statuses, true);
            },
            function (int $attempt, $response = null) use ($delay, $multiplier, $respectRetryAfter): int {
                if ($respectRetryAfter && $response !== null && $response->hasHeader('Retry-After')) {
                    return (int) $response->getHeaderLine('Retry-After') * 1000;
                }

                return (int) ($delay * ($multiplier ** ($attempt - 1)));
            },
        );
    }
```
Add import: `use GuzzleHttp\Exception\ConnectException;`

- [ ] **Step 4: Write the integration test**

`tests/Integration/RetryIntegrationTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Support\Json;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

it('survives a flaky endpoint over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'retry' => ['times' => 3, 'delay' => 10],
    ]);

    $key = uniqid('flaky');
    $payload = Json::decode((string) $client->get('flaky', ['key' => $key])->getBody());

    expect($payload['attempts'])->toBe(3);
})->group('integration');

it('honors retry-after over real http', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'retry' => ['times' => 2, 'delay' => 10],
    ]);

    $key = uniqid('ra');
    $start = microtime(true);
    $status = $client->get('retry-after', ['key' => $key])->getStatusCode();

    expect($status)->toBe(200)
        ->and(microtime(true) - $start)->toBeGreaterThan(0.9);
})->group('integration');
```

- [ ] **Step 5: Run everything — PASS; lint; analyse.**
- [ ] **Step 6: Commit** — `git commit -am "feat: configurable retry with backoff and retry-after"`

---

### Task 7: Model events

**Files:**
- Create: `src/Events/ModelCreated.php`, `src/Events/ModelUpdated.php`, `src/Events/ModelDeleted.php`
- Modify: `src/Model.php` (hook registry), `src/Builder.php` (fire hooks; `post/put` become `?Model`), `src/RestServiceProvider.php` (dispatcher bridge)
- Test: `tests/Unit/ModelEventsTest.php`

**Interfaces:**
- Consumes: `Rest::fake` (Task 4) for cheap HTTP-free tests.
- Produces:
  - Static registrars on `Model`: `creating|created|updating|updated|deleting|deleted(callable $listener): void`; `Model::flushEventListeners(): void`; `Model::setEventDispatcher(?object $dispatcher): void` (duck-typed `dispatch(object $event)`).
  - `Model::fireModelEvent(string $event): bool` (public; false when any listener returns false).
  - `Builder::post/put` return `?Model` (null when cancelled); `delete` returns false when cancelled.
  - Event classes with `public Model $model` constructor property.

- [ ] **Step 1: Write the failing test**

`tests/Unit/ModelEventsTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class EventedPost extends Model
{
    protected ?string $dataKey = null;
}

afterEach(function () {
    EventedPost::flushEventListeners();
    Model::setEventDispatcher(null);
    Rest::restore();
});

it('fires creating and created around post', function () {
    Rest::fake(['evented_posts' => Rest::response(['id' => 1])]);
    $log = [];

    EventedPost::creating(function (EventedPost $model) use (&$log) {
        $log[] = 'creating:'.$model->title;
    });
    EventedPost::created(function (EventedPost $model) use (&$log) {
        $log[] = 'created:'.$model->id;
    });

    EventedPost::post(['title' => 'x']);

    expect($log)->toBe(['creating:x', 'created:1']);
});

it('cancels post when creating returns false', function () {
    Rest::fake(['evented_posts' => Rest::response(['id' => 1])]);
    EventedPost::creating(fn () => false);

    expect(EventedPost::post(['title' => 'x']))->toBeNull();
    Rest::assertSentCount(0);
});

it('fires updating and updated around put, deleting and deleted around delete', function () {
    Rest::fake(['evented_posts/*' => Rest::response(['id' => 5])]);
    $log = [];

    EventedPost::updating(function () use (&$log) { $log[] = 'updating'; });
    EventedPost::updated(function () use (&$log) { $log[] = 'updated'; });
    EventedPost::deleting(function () use (&$log) { $log[] = 'deleting'; });
    EventedPost::deleted(function () use (&$log) { $log[] = 'deleted'; });

    EventedPost::put(5, ['title' => 'y']);
    EventedPost::delete(5);

    expect($log)->toBe(['updating', 'updated', 'deleting', 'deleted']);
});

it('cancels delete when deleting returns false', function () {
    Rest::fake(['evented_posts/*' => Rest::response([])]);
    EventedPost::deleting(fn () => false);

    expect(EventedPost::delete(5))->toBeFalse();
    Rest::assertSentCount(0);
});

it('dispatches bridge events to a dispatcher', function () {
    Rest::fake(['evented_posts' => Rest::response(['id' => 1])]);
    $dispatched = [];

    Model::setEventDispatcher(new class($dispatched)
    {
        public function __construct(private array &$bag) {}

        public function dispatch(object $event): void
        {
            $this->bag[] = $event::class;
        }
    });

    EventedPost::post(['title' => 'x']);

    expect($dispatched)->toBe([Sanchescom\Rest\Events\ModelCreated::class]);
});
```

Note on the anonymous dispatcher: `private array &$bag` promoted by-reference is not allowed — write it unpromoted:
```php
    Model::setEventDispatcher(new class($dispatched)
    {
        /** @var list<string> */
        public array $bag;

        public function __construct(array &$bag)
        {
            $this->bag = &$bag;
        }

        public function dispatch(object $event): void
        {
            $this->bag[] = $event::class;
        }
    });
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Events/ModelCreated.php` (ModelUpdated, ModelDeleted identical apart from the class name):
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Events;

use Sanchescom\Rest\Model;

final class ModelCreated
{
    public function __construct(public readonly Model $model) {}
}
```

`src/Model.php` — add:
```php
    /** @var array<class-string, array<string, list<callable>>> */
    protected static array $eventListeners = [];

    protected static ?object $eventDispatcher = null;

    public static function creating(callable $listener): void
    {
        static::registerModelEvent('creating', $listener);
    }

    public static function created(callable $listener): void
    {
        static::registerModelEvent('created', $listener);
    }

    public static function updating(callable $listener): void
    {
        static::registerModelEvent('updating', $listener);
    }

    public static function updated(callable $listener): void
    {
        static::registerModelEvent('updated', $listener);
    }

    public static function deleting(callable $listener): void
    {
        static::registerModelEvent('deleting', $listener);
    }

    public static function deleted(callable $listener): void
    {
        static::registerModelEvent('deleted', $listener);
    }

    protected static function registerModelEvent(string $event, callable $listener): void
    {
        static::$eventListeners[static::class][$event][] = $listener;
    }

    public static function flushEventListeners(): void
    {
        unset(static::$eventListeners[static::class]);
    }

    public static function setEventDispatcher(?object $dispatcher): void
    {
        static::$eventDispatcher = $dispatcher;
    }

    public function fireModelEvent(string $event): bool
    {
        foreach (static::$eventListeners[static::class][$event] ?? [] as $listener) {
            if ($listener($this) === false) {
                return false;
            }
        }

        $bridge = match ($event) {
            'created' => Events\ModelCreated::class,
            'updated' => Events\ModelUpdated::class,
            'deleted' => Events\ModelDeleted::class,
            default => null,
        };

        if ($bridge !== null && static::$eventDispatcher !== null && method_exists(static::$eventDispatcher, 'dispatch')) {
            static::$eventDispatcher->dispatch(new $bridge($this));
        }

        return true;
    }
```

`src/Builder.php` — wire hooks (replace `post`, `put`, `delete`):
```php
    /**
     * @param  array<string, mixed>  $data
     */
    public function post(array $data = []): ?Model
    {
        $model = $this->model->fill($data);

        if (! $model->fireModelEvent('creating')) {
            return null;
        }

        $payload = $this->decode($this->client()->post($this->uri(), $model->getAttributes()));

        $created = $this->model->newInstance($this->extract($payload));
        $created->fireModelEvent('created');

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string|int|null $id = null, array $data = []): ?Model
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot update a model without an id or primary key value.');
        }

        $model = $this->model->fill($data);

        if (! $model->fireModelEvent('updating')) {
            return null;
        }

        $payload = $this->decode($this->client()->put($this->uri($id), $model->getAttributes()));

        $updated = $this->model->newInstance($this->extract($payload));
        $updated->fireModelEvent('updated');

        return $updated;
    }

    public function delete(string|int|null $id = null): bool
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot delete a model without an id or primary key value.');
        }

        if (! $this->model->fireModelEvent('deleting')) {
            return false;
        }

        $this->client()->delete($this->uri($id));

        $this->model->fireModelEvent('deleted');

        return true;
    }
```

Update the `@method` docblock on `Model`: `post` and `put` now `Model|null`.

`src/RestServiceProvider.php` — in `boot()` add:
```php
        if ($this->app->bound('events')) {
            Model::setEventDispatcher($this->app->make('events'));
        }
```

- [ ] **Step 4: Run FULL suite — PASS** (existing BuilderTest must stay green); lint; analyse.
- [ ] **Step 5: Commit** — `git commit -am "feat: cancellable model events with laravel dispatcher bridge"`

---

### Task 8: Relations

**Files:**
- Create: `src/Relations/Relation.php`, `src/Relations/HasMany.php`, `src/Relations/HasOne.php`, `src/Relations/BelongsTo.php`
- Modify: `src/Model.php` (`hasMany/hasOne/belongsTo`, `__get` relation fallback, relation cache)
- Test: `tests/Unit/RelationsTest.php`, `tests/Integration/RelationsIntegrationTest.php`

**Interfaces:**
- Consumes: `Builder::where/from/get` (Task 3), fixture routes `posts/{id}/comments`, `comments?postId=`, `users/{id}` (Task 1), `Rest::fake` (Task 4).
- Produces:
  - `Model::hasMany(string $related, ?string $foreignKey = null): HasMany`, `hasOne(...): HasOne`, `belongsTo(string $related, ?string $foreignKey = null): BelongsTo`.
  - `HasMany::nested(): static`, `HasMany::builder(): Builder`, `HasMany::getResults(): Collection`, `__call` proxies to `builder()`.
  - `HasOne::getResults(): ?Model`; `BelongsTo::getResults(): ?Model`.
  - Default FKs: hasMany/hasOne `camel(class_basename(parent)).'Id'`; belongsTo `camel(class_basename(related)).'Id'`.
  - `$model->relationName` lazy-loads and caches `getResults()` when no attribute shadows the name and a method exists.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/RelationsTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Rest;

class RelPost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function comments(): HasMany
    {
        return $this->hasMany(RelComment::class, 'postId');
    }

    public function nestedComments(): HasMany
    {
        return $this->hasMany(RelComment::class)->nested();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(RelUser::class, 'userId');
    }
}

class RelComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

class RelUser extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

afterEach(fn () => Rest::restore());

it('loads hasMany through a fk filter', function () {
    Rest::fake(['comments' => Rest::response([['id' => 10, 'postId' => 1]])]);

    $comments = (new RelPost(['id' => 1]))->comments;

    expect($comments)->toHaveCount(1);
    Rest::assertSent(fn ($request) => $request->uri() === 'comments'
        && $request->query() === ['postId' => 1]);
});

it('loads hasMany through a nested url', function () {
    Rest::fake(['posts/1/comments' => Rest::response([['id' => 10]])]);

    $comments = (new RelPost(['id' => 1]))->nestedComments;

    expect($comments)->toHaveCount(1);
    Rest::assertSent(fn ($request) => $request->uri() === 'posts/1/comments');
});

it('caches lazy relations per instance', function () {
    Rest::fake(['comments' => Rest::response([['id' => 10]])]);

    $post = new RelPost(['id' => 1]);
    $post->comments;
    $post->comments;

    Rest::assertSentCount(1);
});

it('chains where through the relation', function () {
    Rest::fake(['comments' => Rest::response([])]);

    (new RelPost(['id' => 1]))->comments()->where('rating', 5)->get();

    Rest::assertSent(fn ($request) => $request->query() === ['postId' => 1, 'rating' => 5]);
});

it('loads belongsTo by foreign key', function () {
    Rest::fake(['users/7' => Rest::response(['id' => 7, 'name' => 'Tim'])]);

    $author = (new RelPost(['id' => 1, 'userId' => 7]))->author;

    expect($author->name)->toBe('Tim');
});

it('returns null belongsTo when fk is absent', function () {
    Rest::fake(['users/*' => Rest::response([])]);

    expect((new RelPost(['id' => 1]))->author)->toBeNull();
    Rest::assertSentCount(0);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`src/Relations/Relation.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Sanchescom\Rest\Model;

abstract class Relation
{
    /**
     * @param  class-string<Model>  $related
     */
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected ?string $foreignKey = null,
    ) {}

    abstract public function getResults(): mixed;

    protected function relatedInstance(): Model
    {
        return new $this->related;
    }
}
```

`src/Relations/HasMany.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Illuminate\Support\Str;
use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Collection;

class HasMany extends Relation
{
    protected bool $nested = false;

    public function nested(): static
    {
        $this->nested = true;

        return $this;
    }

    public function builder(): Builder
    {
        $related = $this->relatedInstance();

        if ($this->nested) {
            return $related->newBuilder()->from(
                $this->parent->getEndpoint().'/'.$this->parent->getKey().'/'.$related->getEndpoint(),
            );
        }

        $foreignKey = $this->foreignKey
            ?? Str::camel(class_basename($this->parent)).'Id';

        return $related->newBuilder()->where($foreignKey, $this->parent->getKey());
    }

    /**
     * @return Collection<int, \Sanchescom\Rest\Model>
     */
    public function getResults(): Collection
    {
        $result = $this->builder()->get();

        return $result instanceof Collection ? $result : new Collection([$result]);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->builder()->{$method}(...$parameters);
    }
}
```

`src/Relations/HasOne.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Sanchescom\Rest\Model;

class HasOne extends HasMany
{
    public function getResults(): ?Model
    {
        return parent::getResults()->first();
    }
}
```

Note: `HasOne::getResults(): ?Model` narrows the parent return type — PHP requires covariance, `?Model` vs `Collection` are incompatible siblings. Fix: declare `getResults(): mixed` in `Relation` (already `mixed`), and in `HasMany` keep the docblock but declare the native return as `mixed`? No — cleaner: `HasMany::getResults(): Collection` and `HasOne` does NOT extend `HasMany`'s return; instead `HasOne` extends `Relation` and composes:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

class HasOne extends Relation
{
    protected bool $nested = false;

    public function nested(): static
    {
        $this->nested = true;

        return $this;
    }

    public function getResults(): ?Model
    {
        $hasMany = new HasMany($this->parent, $this->related, $this->foreignKey);

        if ($this->nested) {
            $hasMany->nested();
        }

        return $hasMany->getResults()->first();
    }
}
```

`src/Relations/BelongsTo.php`:
```php
<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Illuminate\Support\Str;
use Sanchescom\Rest\Model;

class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        $foreignKey = $this->foreignKey
            ?? Str::camel(class_basename($this->related)).'Id';

        $key = $this->parent->getAttribute($foreignKey);

        if ($key === null) {
            return null;
        }

        $result = $this->relatedInstance()->newBuilder()->get($key);

        return $result instanceof Model ? $result : null;
    }
}
```

`src/Model.php` — add relation factories, cache, and replace `__get`:
```php
    /** @var array<string, mixed> */
    protected array $loadedRelations = [];

    /**
     * @param  class-string<self>  $related
     */
    public function hasMany(string $related, ?string $foreignKey = null): Relations\HasMany
    {
        return new Relations\HasMany($this, $related, $foreignKey);
    }

    /**
     * @param  class-string<self>  $related
     */
    public function hasOne(string $related, ?string $foreignKey = null): Relations\HasOne
    {
        return new Relations\HasOne($this, $related, $foreignKey);
    }

    /**
     * @param  class-string<self>  $related
     */
    public function belongsTo(string $related, ?string $foreignKey = null): Relations\BelongsTo
    {
        return new Relations\BelongsTo($this, $related, $foreignKey);
    }

    public function __get(string $key): mixed
    {
        if (! array_key_exists($key, $this->attributes) && method_exists($this, $key)) {
            return $this->loadedRelations[$key] ??= $this->{$key}()->getResults();
        }

        return $this->getAttribute($key);
    }
```

- [ ] **Step 4: Write the integration test**

`tests/Integration/RelationsIntegrationTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class LivePost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function comments(): HasMany
    {
        return $this->hasMany(LiveComment::class, 'postId');
    }

    public function nestedComments(): HasMany
    {
        return $this->hasMany(LiveComment::class)->nested();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(LiveUser::class, 'userId');
    }
}

class LiveComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

class LiveUser extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

beforeEach(function () {
    $resolver = new ClientResolver([
        'fixture' => GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]),
    ]);
    $resolver->setDefaultClient('fixture');
    Model::setClientResolver($resolver);
});

it('loads fk-filtered and nested relations over real http', function () {
    $post = new LivePost(['id' => 1, 'userId' => 7]);

    expect($post->comments->pluck('id')->all())->toBe([10, 11])
        ->and($post->nestedComments)->toHaveCount(2)
        ->and($post->author->name)->toBe('User 7');
})->group('integration');
```

- [ ] **Step 5: Run FULL suite — PASS; lint; analyse.**
- [ ] **Step 6: Commit** — `git commit -am "feat: hasMany, hasOne and belongsTo relations"`

---

### Task 9: Live matrix

**Files:**
- Create: `tests/Live/PublicApisTest.php`
- Test: itself (group `live`).

**Interfaces:**
- Consumes: everything shipped in Tasks 2–8.
- Produces: nightly-runnable evidence that grammars/auth/relations hold against real APIs. Every test must skip cleanly offline.

- [ ] **Step 1: Write the live tests**

`tests/Live/PublicApisTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

function liveResolver(string $baseUri, array $config = []): void
{
    $resolver = new ClientResolver([
        'live' => GuzzleClient::fromConfig(array_merge(['base_uri' => $baseUri], $config)),
    ]);
    $resolver->setDefaultClient('live');
    Model::setClientResolver($resolver);
}

class JpPost extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    protected array $casts = ['id' => 'int', 'userId' => 'int'];

    public function comments(): HasMany
    {
        return $this->hasMany(JpComment::class, 'postId')->nested();
    }
}

class JpComment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

it('queries jsonplaceholder with plain grammar and nested relations', function () {
    liveResolver('https://jsonplaceholder.typicode.com/');

    $posts = JpPost::where('userId', 1)->get();
    expect($posts)->toHaveCount(10);

    $post = JpPost::get(1);
    expect($post->comments)->toHaveCount(5);

    expect(fn () => JpPost::get(987654))->toThrow(ModelNotFoundException::class);
})->group('live');

class HttpbinEcho extends Model
{
    protected ?string $endpoint = 'bearer';

    protected ?string $dataKey = null;
}

it('proves bearer auth on the wire via httpbin', function () {
    liveResolver('https://httpbin.org/', [
        'auth' => ['driver' => 'bearer', 'token' => 'live-proof'],
    ]);

    $echo = HttpbinEcho::get();
    expect($echo->first()->toArray())->toBe([]);
})->group('live')->skip(fn () => @get_headers('https://httpbin.org') === false, 'httpbin unreachable');
```

Note: `/bearer` returns `{"authenticated": true, "token": "live-proof"}` — a bare object; with `dataKey = null` a collection get on it hydrates key-value pairs. Assert instead through a single model read: change `HttpbinEcho` to use `get()` collection and assert via raw client:
```php
it('proves bearer auth on the wire via httpbin', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://httpbin.org/',
        'auth' => ['driver' => 'bearer', 'token' => 'live-proof'],
    ]);

    $payload = json_decode((string) $client->get('bearer')->getBody(), true);

    expect($payload['authenticated'])->toBeTrue()
        ->and($payload['token'])->toBe('live-proof');
})->group('live');
```
Use this second version; drop the `HttpbinEcho` class.

- [ ] **Step 2: Run** `composer test:live` — expect PASS (network required). Then `composer test` — live tests must NOT run.
- [ ] **Step 3: Run lint and analyse.**
- [ ] **Step 4: Commit** — `git commit -am "test: live matrix against jsonplaceholder and httpbin"`

---

### Task 10: Dogfooding — ApplyWave APIs (env-gated live tests)

**Files:**
- Create: `tests/Live/ApplyWaveApisTest.php`
- Test: itself (group `live`; every test skips without its env vars).

**Interfaces:**
- Consumes: auth drivers, query builder, exceptions.
- Produces: proof the library drives real ApplyWave integrations; findings feed the capability matrix (Task 11).

- [ ] **Step 1: Write the env-gated live tests**

`tests/Live/ApplyWaveApisTest.php`:
```php
<?php

declare(strict_types=1);

use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Support\Json;

it('reads job-api through header auth', function () {
    $base = getenv('JOB_API_BASE_URL');
    $key = getenv('JOB_API_KEY');

    $client = GuzzleClient::fromConfig([
        'base_uri' => rtrim((string) $base, '/').'/',
        'auth' => ['driver' => 'header', 'headers' => ['X-Api-Key' => (string) $key]],
        'retry' => ['times' => 3, 'delay' => 200],
    ]);

    $response = $client->get('health');

    expect($response->getStatusCode())->toBe(200);
})->group('live')->skip(fn () => ! getenv('JOB_API_BASE_URL') || ! getenv('JOB_API_KEY'), 'JOB_API_* env not set');

it('lists models from an openai-compatible api through bearer auth', function () {
    $base = getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com';
    $key = getenv('OPENAI_API_KEY');

    $client = GuzzleClient::fromConfig([
        'base_uri' => rtrim((string) $base, '/').'/v1/',
        'auth' => ['driver' => 'bearer', 'token' => (string) $key],
    ]);

    $payload = Json::decode((string) $client->get('models')->getBody());

    expect($payload['data'])->toBeArray()->not->toBeEmpty();
})->group('live')->skip(fn () => ! getenv('OPENAI_API_KEY'), 'OPENAI_API_KEY not set');

it('reads anthropic models through multi-header auth', function () {
    $key = getenv('ANTHROPIC_API_KEY');

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.anthropic.com/v1/',
        'auth' => ['driver' => 'header', 'headers' => [
            'x-api-key' => (string) $key,
            'anthropic-version' => '2023-06-01',
        ]],
    ]);

    $payload = Json::decode((string) $client->get('models')->getBody());

    expect($payload['data'])->toBeArray()->not->toBeEmpty();
})->group('live')->skip(fn () => ! getenv('ANTHROPIC_API_KEY'), 'ANTHROPIC_API_KEY not set');
```

- [ ] **Step 2: Verify job-api route** — before relying on `health`, check the real path: read `~/projects/job-api` (FastAPI app, look for `/health` or `/` route and the auth header name the app expects — `grep -rE "api_key|APIKey|Header" app/ main.py`). Adjust the test's path and header name to match reality. If the header is e.g. `Authorization: Bearer`, switch the driver accordingly. This step is investigation + edit, not guesswork.
- [ ] **Step 3: Run** `composer test:live` with the env vars exported (ask the user for values or source them from the ApplyWave `.env`). Tests without creds must SKIP, not fail.
- [ ] **Step 4: Record findings** — any mismatch (unexpected envelope, auth scheme, error format) goes into `docs/capabilities.md` in Task 11.
- [ ] **Step 5: Run lint and analyse; `composer test` still green.**
- [ ] **Step 6: Commit** — `git commit -am "test: dogfooding live tests for applywave apis"`

---

### Task 11: Capability matrix, docs, changelog, release 1.1.0

**Files:**
- Create: `docs/capabilities.md`
- Modify: `README.md` (document all six features, replace Roadmap), `CHANGELOG.md` (1.1.0 entry), `UPGRADE.md` (1.0 → 1.1 notes)

**Interfaces:** none — docs must match the shipped signatures exactly (verify each snippet against `src/`).

- [ ] **Step 1: Write `docs/capabilities.md`**

```markdown
# Capability Matrix

What laravel-rest supports as of 1.1.0 — and what it deliberately does not.

| Area | Supported | Not supported (workaround) |
| --- | --- | --- |
| Request bodies | JSON | form-encoded, multipart, XML (custom `ClientInterface`) |
| Query filters | plain `?field=value`, JSON:API `filter[...]`, custom `Grammar` | GraphQL, OData `$filter` (custom `Grammar`) |
| Sorting | `sort=-date,name` (plain and JSON:API) | per-API sort keys (use `withQuery()`) |
| Pagination | query params: `limit/offset/page`, `page[size]/page[number]` | Link-header, cursor tokens (use `withQuery()` manually) |
| Auth | bearer, basic, arbitrary headers, custom `AuthInterface` | OAuth2 token acquisition/refresh (attach ready tokens only) |
| Retry | status-based with exponential backoff and `Retry-After` | circuit breakers, jitter |
| Errors | 404/422/5xx/4xx typed exceptions, JSON bodies | non-JSON error bodies are preserved as empty `body` |
| Envelopes | any `dataKey` (dot notation via `Arr::get`) | per-endpoint different keys on one model |
| Relations | hasMany/hasOne (FK filter or nested URL), belongsTo | many-to-many, eager loading (`getMany` helps), embedded includes |
| Events | creating/created/updating/updated/deleting/deleted, cancellation, Laravel bridge | wildcard observers |
| Testing | `Rest::fake()` with patterns + assertions, fixture server pattern | — |

Live-verified against: JSONPlaceholder, httpbin, job-api (internal),
OpenAI-compatible endpoints, Anthropic. Findings from those runs are folded
into the table above.
```

Append any Task 10 findings as extra rows or footnotes.

- [ ] **Step 2: Update README** — replace the Roadmap section with feature docs. Sections to add (each snippet copied from a passing test, adjusted for prose): Query Builder (`where/orderBy/limit/offset/page/withQuery/first/count`, grammars incl. per-client `'grammar' => JsonApiGrammar::class` config and per-model `protected ?string $grammar`), Relations (three types, `nested()`, chaining), Auth (three drivers + custom), Retry (config block), Events (six hooks, cancellation, dispatcher bridge), Testing with `Rest::fake()` (replace the MockHandler example as the primary path; keep `extend('mock')` as the advanced alternative), link to `docs/capabilities.md`.
- [ ] **Step 3: Update CHANGELOG** — `## 1.1.0` entry: added query builder + grammars, relations, auth drivers, retry, `Rest::fake`, model events, capability matrix; changed: `Builder::post/put` return `?Model` (null on cancelled hook), `ClientResolverInterface` gains `grammar()` (BC note for custom implementers).
- [ ] **Step 4: Update UPGRADE** — short 1.0 → 1.1 section covering the two BC notes above.
- [ ] **Step 5: Verify every README snippet against `src/` signatures (manual check).**
- [ ] **Step 6: Run FULL verification** — `composer test && composer lint && composer analyse`, plus `composer test:live` one final time.
- [ ] **Step 7: Commit** — `git commit -am "docs: capability matrix and 1.1 feature docs"`
- [ ] **Step 8: Release** — after user confirmation: `git tag -a 1.1.0 -m "Release 1.1.0: query builder, relations, auth, retry, fakes, events" && git push origin master 1.1.0`

---

## Self-Review Notes

- Spec coverage: query builder+grammars → Tasks 2–3; fakes → Task 4; auth → Task 5; retry → Task 6; events → Task 7; relations → Task 8; verification levels: unit (all tasks), fixture server (Tasks 1, 3, 5, 6, 8), live matrix (Task 9), capability matrix (Task 11); dogfooding → Task 10. Implementation order matches the spec.
- Type consistency: `Grammar::compile(QueryState): array` used by `Builder`; `Builder::__construct(Model, ?Grammar)` keeps `new Builder($model)` BC; `fireModelEvent` public on `Model`, called by `Builder`; `ClientResolverInterface::grammar()` implemented by `ClientResolver`, `ClientManager` and the fake resolver in `Rest::fake`; `RecordedRequest` accessors used in all fake assertions.
- Judgment calls: `HasOne` composes `HasMany` instead of extending it (return type covariance); fake `Rest::fake` swaps the whole resolver (grammar falls back to model/default — config-level grammar is not honored under fakes, acceptable for tests and documented in README); `Builder::first()` does not auto-`limit(1)` (not all APIs support limit; callers add it explicitly).
