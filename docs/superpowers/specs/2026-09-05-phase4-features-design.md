# Laravel Rest — Phase 4 Features Design

**Date:** 2026-09-05
**Target release:** 1.1.0
**Depends on:** 1.0.0 (modernization, phases 1–3 — shipped)

## Goal

Add the feature layer on top of the 1.0 core: query builder with pluggable
grammars, relations, auth drivers, retry policies, test fakes and model
events — verified against real-world APIs, with the supported contract
documented explicitly.

## Non-Goals

- Form-encoded or XML request bodies (JSON only; documented in capability matrix).
- Link-header / cursor pagination helpers (query-parameter pagination only).
- OAuth2 flows (token acquisition is the caller's job; we only attach tokens).
- Response caching.

## Decisions Made (with user, 2026-09-05)

1. **Scope:** all six features go into 1.1 in one plan, ordered.
2. **Query builder:** pluggable `Grammar` classes; `PlainGrammar` default,
   `JsonApiGrammar` shipped; grammar set per client config or per model.
3. **Relations:** both strategies, chosen at declaration site — FK filter via
   query builder (default) or nested URL via `->nested()`.
4. **Fakes:** `Rest::fake()` in `Http::fake()` style with URI patterns,
   recorded requests and `assertSent`/`assertNotSent`/`assertSentCount`.
5. **Events:** static callback hooks on the model (standalone-friendly, zero
   new deps); Laravel provider bridges hooks into the event dispatcher.
   `false` from a `*ing` hook cancels the operation without an HTTP call.
6. **Verification:** four-level strategy plus dogfooding against ApplyWave
   APIs (see below).

## Architecture

### 1. Query builder + grammars

- `Builder` gains fluent state: `wheres` (field, operator, value), `orders`
  (field, direction), `limit`, `offset`, `page`, `extra` (raw query params).
- New `src/Query/Grammar.php` interface: `compile(QueryState $state): array`
  (returns query-string params). Implementations:
  - `src/Query/PlainGrammar.php` — `?field=value&sort=-date&limit=10&offset=20`
  - `src/Query/JsonApiGrammar.php` — `?filter[field]=value&sort=-date&page[size]=10&page[number]=2`
- Grammar resolution order: model `protected ?string $grammar` → client config
  `'grammar' => ClassName` → `PlainGrammar`.
- `Model::where(...)` / `orderBy` / `limit` / `offset` / `page` / `withQuery`
  forward to a fresh `Builder` via existing `__callStatic`. Terminal methods:
  `get()`, `first()`, `count()` (client-side count of `get()`).
- BC: `get($id)`, `post`, `put`, `delete` signatures unchanged.

### 2. Relations

- `src/Relations/Relation.php` (abstract), `HasMany.php`, `HasOne.php`,
  `BelongsTo.php`.
- Declared as methods: `$this->hasMany(Comment::class, 'postId')`,
  `$this->belongsTo(User::class, 'userId')`. Default FK name:
  `camel(class_basename(parent)).'Id'` (Post → `postId`) for hasMany;
  for belongsTo: `camel(class_basename(related)).'Id'` (User → `userId`).
  Explicit FK argument always wins — APIs disagree on naming.
- `HasMany` default: `Comment::where($fk, $parentKey)` (goes through grammar).
  `->nested()`: `GET {parent_endpoint}/{parent_key}/{child_endpoint}`.
- Lazy access `$post->comments` resolves via `__get` fallback when no
  attribute with that name exists but a relation method does; result cached
  per instance. `$post->comments()` returns the relation (proxies builder,
  so `->where()->get()` works).
- `BelongsTo`: `Child::get($this->{$fk})`, cached.

### 3. Auth drivers

- `src/Auth/AuthInterface.php`: `authenticate(RequestInterface): RequestInterface`.
- Shipped: `BearerAuth` (Authorization: Bearer), `BasicAuth`, `HeaderAuth`
  (arbitrary header name/value — covers x-api-key, api-key, anthropic-style).
- Config: `'auth' => ['driver' => 'bearer'|'basic'|'header'|FQCN, ...params]`.
- Applied as Guzzle middleware in `GuzzleClient::fromConfig()`; custom FQCN
  is instantiated with the config array.

### 4. Retry policies

- Config: `'retry' => ['times' => 3, 'delay' => 100, 'multiplier' => 2,
  'statuses' => [429, 500, 502, 503], 'respect_retry_after' => true]`.
- Guzzle retry middleware in `fromConfig()`: exponential backoff
  (`delay * multiplier^attempt`), honors `Retry-After` header when enabled,
  retries connect exceptions. Off by default (no `retry` key — no middleware).

### 5. Fakes

- `src/Rest.php` (static entry + Laravel facade `RestFacade` optional):
  - `Rest::fake(array $map)` — pattern (`posts/*`) → `Rest::response(array|string $body, int $status, array $headers)`.
  - Installs `FakeClient` (implements `ClientInterface`) as resolver for all
    clients; records every request (method, uri, query, data).
  - Unmatched URI → descriptive `RestException` (strict by default).
  - `Rest::assertSent(Closure)`, `assertNotSent(Closure)`, `assertSentCount(int)`,
    `Rest::recorded(): array`.
  - `Rest::restore()` puts the previous resolver back (auto in Laravel via
    test teardown is caller's responsibility).

### 6. Model events

- Static hook registry on `Model`: `creating/created/updating/updated/
  deleting/deleted`, registered via `Post::creating(Closure)`.
- `Builder::post/put/delete` fire hooks around HTTP calls; `false` from a
  `*ing` hook aborts (post/put return `null`, delete returns `false`).
- `Model::flushEventListeners()` for test isolation.
- Laravel bridge in `RestServiceProvider::boot()`: hooks additionally dispatch
  `Sanchescom\Rest\Events\ModelCreated|Updated|Deleted` through the app
  dispatcher when a dispatcher is bound.

## Verification Strategy (four levels + dogfooding)

1. **Unit (mocked)** — per component, as in phases 1–3. Runs on every PR.
2. **Local fixture server** — PHP built-in server (`php -S`) with a router
   script in `tests/Fixtures/server.php`, started/stopped by a Pest bootstrap
   for the `integration` group. Simulates deterministically: envelopes
   (`data`, none, nested key), nested routes, bearer/basic/header auth
   (asserts the header arrived), `503,503,200` sequence for retry,
   `Retry-After`, malformed JSON, 422 with errors. Real sockets, no external
   deps, runs in CI on every PR.
3. **Live matrix (`--group=live`, opt-in / nightly)** — curated public APIs:
   - JSONPlaceholder — plain REST, no auth (already proven for 1.0);
   - httpbin.org — wire-level echo: `/bearer`, `/basic-auth`, `/status/*`, `/delay`;
   - GitHub API — bearer + nested routes (`repos/{owner}/{repo}/issues`);
   - a JSON:API endpoint — validates `JsonApiGrammar` against reality.
4. **Capability matrix doc** (`docs/capabilities.md`) — explicit supported /
   unsupported list (JSON bodies yes, form-encoded no; query-param pagination
   yes, Link-header no; ...). Every live-matrix incompatibility either gets
   fixed or lands here. This is also the backlog feed for 1.2+.

**Dogfooding (release gate for 1.1):** wire laravel-rest into real ApplyWave
integrations — candidates, each exercising a different scheme:

| API | Auth scheme | Exercises |
| --- | --- | --- |
| job-api (internal FastAPI) | api-key header | HeaderAuth, polling reads, envelopes |
| Apollo.io | `X-Api-Key` | HeaderAuth + retry (replaces hand-rolled retry config) |
| OpenAI / Groq / Gemini (openai-compat) | `Authorization: Bearer` | BearerAuth, error formats |
| Anthropic | `x-api-key` + version header | HeaderAuth with multiple headers |
| Brevo | `api-key` header | HeaderAuth, 4xx error bodies |

Minimum bar: job-api + one external API consumed through laravel-rest in a
real code path (or a faithful spike in the ApplyWave repo), findings fed back
into the capability matrix before tagging 1.1.0.

## Implementation Order

1. Verification infrastructure (fixture server harness, `integration` and
   `live` Pest groups, CI wiring)
2. Query builder + grammars
3. Fakes (needed to test everything after cheaply)
4. Auth drivers
5. Retry policies
6. Model events
7. Relations (builds on query builder)
8. Live matrix + dogfooding pass
9. Capability matrix + docs + release 1.1.0

## Testing Requirements

Every task: TDD (failing test first), `composer test` + `composer lint` +
`composer analyse` green before commit. Fixture-server integration tests
required for auth, retry, grammars and relations. Live group excluded from
default CI; runs nightly and before the release tag.
