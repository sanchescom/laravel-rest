# Changelog

All notable changes to this project will be documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/) and this
project adheres to [Semantic Versioning](https://semver.org/).

## 1.6.0 (unreleased)

### Added

- **Eager loading** — `Builder::with()` and `Collection::load()` load
  relations for a whole result set: concurrently per parent by default, or in
  one `whereIn` request for relations marked `->batch()`. Supports dot nesting
  (`comments.author`) and constraint closures, and applies to `get()`,
  `first()`, `getMany()`, `paginate()`, `simplePaginate()` and `lazy()` pages.
- **`whereIn()`** — membership filter rendered by every built-in grammar
  (`field=1,2`, `filter[field]=1,2`, `field__in=1,2`); `ConfigurableGrammar`
  gains `'in' => 'comma' | 'array'`.
- **`Model::setRelation()` / `Model::relationFor()`** and
  `Relation::eagerLoad()` for custom relations.

### Changed

- **`where($field, 'in', [...])`** now renders like `whereIn()` instead of the
  unusable `field[in][0]=…`. Custom `Grammar` implementations should handle
  the `in` operator.
- **`Rest::fake()`** matches patterns against the path of URIs that carry a
  query string; `RecordedRequest::query()` holds the parsed values.
- **`Relation` base class** gains `batch()` and `eagerLoad()`; custom relation
  subclasses declaring members with those names must match the new
  signatures.

## 1.5.0

### Added

- **`lazy(int $chunkSize = 100)`** — returns a `LazyCollection` that walks
  every page with the 1.4 pagination rules (`page`/`offset` style, `next`,
  `has_more`, full-page inference) and stops on an empty page. Throws
  `RestException` when the API returns the same page twice (keyed models only),
  so a misconfigured page parameter cannot loop forever.
- **Request memoization** — opt-in (`'memoize' => true` / `REST_MEMOIZE`, or
  `Rest::memoize()`): identical GET requests during one application request
  hit the API once while still returning fresh model instances. Writes forget
  the written model's entries; `withoutCache()` and `lazy()` bypass it; the
  service provider resets it on Octane `RequestReceived` and queue
  `JobProcessing` (plus Octane task and tick workers). `Rest::fake()` /
  `Rest::restore()` and `Rest::flushMemo()` clear it.

### Changed

- **Invalidation now happens before write events.** `post()`, `put()` and
  `delete()` flush the model's response cache (and memo) before firing
  `created` / `updated` / `deleted` and the `ModelCreated` / `ModelUpdated` /
  `ModelDeleted` dispatcher events, so listeners that re-read the model see
  fresh data instead of the pre-write cache entry.

## 1.4.0

### Added

- **Server-side pagination** — `Builder::paginate()` returns a
  `LengthAwarePaginator` built from response metadata; `simplePaginate()`
  returns a `Paginator` driven by a next link, a has-more flag, or full-page
  inference. Configured per client via `'pagination'` (presets `laravel`,
  `django`, `jsonapi`, or explicit `total` / `next` / `has_more` dot paths and
  `style` `page`|`offset`), overridable with the model `$pagination` property.
- **`PaginationConfigResolverInterface`** — optional interface implemented by
  `ClientManager`, `ClientResolver` and the `Rest::fake()` resolver (which
  delegates to the previous resolver). `ClientResolverInterface` is unchanged.

## 1.3.1

### Fixed

- **`getMany` transport failures** — a connection error (timeout, refused,
  DNS) on any request in the pool surfaced as a `TypeError` from an undefined
  response slot. It now rethrows the original Guzzle `ConnectException`,
  matching `get()`.

## 1.3.0

### Added

- **`ConfigurableGrammar`** — a grammar driven entirely by a `'query'` config
  array (or preset string) with no custom class required. Supports four sort
  styles (`dash`, `suffix`, `separate`, `array`), three filter styles
  (`plain`, `brackets`, `django`), parameter renaming with dot-notation nesting,
  and field-name casing (`snake`/`camel`).
- **Presets** — `'query' => 'jsonapi'` (brackets filters, dash sort,
  `page[size]`/`page[number]`/`page[offset]`) and `'query' => 'django'`
  (django filters, dash sort, renames `sort` → `ordering` and `limit` → `page_size`).
  Presets can be mixed with overrides via `'preset' => 'jsonapi'` inside an array config.
- **`queryConfig()` resolution** — `ClientManager` reads `rest.clients.<name>.query`
  from the config file; `ClientResolver` exposes `setQueryConfig()`. Resolution
  order: model `$grammar` → client `'grammar'` → client `'query'` → `PlainGrammar`.
- **Dynamic headers** — `withHeaders(array $headers)` chain method merges
  request-time headers over the model-level `protected array $headers` property.
  The merge is applied per chain call; the model property is never mutated.
  Cache keys encode the merged header set so each header combination gets its
  own cache entry.
- **`errors_key`** — client config key (dot notation, e.g. `'meta.errors'`)
  that tells `ValidationException::errors()` where to find field errors in the
  response body. Default remains `'errors'`.
- **`update_method`** — client config key (`'put'` | `'patch'`) that controls
  the HTTP verb used by `Builder::put()`. Default remains `'put'`.
- **`requestDataKey`** — model property (`protected ?string $requestDataKey`)
  that wraps POST/PUT/PATCH bodies in a named key (e.g. `'data'`). Independent
  of the read-side `$dataKey`.

### Changed (breaking for custom `ClientResolverInterface` implementations)

`ClientResolverInterface` gains one new method:

```php
public function queryConfig(?string $name = null): array|string|null;
```

Any class that implements this interface must add this method. Return `null`
to fall back to `PlainGrammar` (same semantics as returning `null` from
`grammar()`). The two built-in implementations (`ClientManager` and
`ClientResolver`) already implement it.

## 1.2.0

### Added

- **Response caching** — PSR-16-backed GET cache. Enable per model with
  `protected ?int $cacheTtl = <seconds>` or per chain with
  `withCache(?int $ttl = null)` / `withoutCache()`.
- **`Model::setCacheStore(?CacheInterface $store, int $defaultTtl = 300)`** —
  registers the PSR-16 store used by all models; called automatically at boot
  from `config/rest.php` `cache.store` / `cache.ttl` when running inside
  Laravel. Set `'cache' => false` to skip wiring.
- **`Model::flushCache()`** — O(1) version-key bump that atomically invalidates
  all cached entries for that model class. Safe no-op when no store is set.
- **Write-through invalidation** — successful `post`, `put`, and `delete` calls
  automatically call `flushCache()` on the model. Cancelled writes (event
  listener returns `false`) do not flush.
- **`CachingClient`** — internal decorator (`src/Cache/CachingClient.php`) that
  wraps any `ClientInterface`. Cache keys encode
  `client | model | version | uri | compiled-query` so each page, filter
  combination, and model class gets its own entry.
- **`Rest::fake()` interplay** — the fake is the inner client; `CachingClient`
  wraps it, so `Rest::assertSentCount()` reflects actual HTTP traffic and proves
  cache hits.

### No breaking changes

1.2 is fully additive. Existing models without `$cacheTtl` and existing chains
without `withCache()` are unaffected.

## 1.1.0

### Added

- **Query builder** — `where()`, `orderBy()`, `limit()`, `offset()`, `page()`,
  `withQuery()`, `first()`, `count()` fluent methods on all models.
- **Grammar system** — `PlainGrammar` (default: `?field=value`, `limit=`,
  `offset=`, `page=`, `sort=`) and `JsonApiGrammar` (`filter[...]`,
  `page[size]`, `page[number]`). Grammar is resolved per-model (`$grammar`
  property), per-client config key (`'grammar' => FQCN`), or falls back to
  `PlainGrammar`. Custom grammars implement
  `Sanchescom\Rest\Query\Grammar`.
- **Relations** — `hasMany()`, `hasOne()`, `belongsTo()` on `Model`; lazy
  loading via `__get` with per-instance caching (including `null`); `.nested()`
  on `HasMany`/`HasOne` switches from FK filter to nested URL
  (`parent/id/related`); relations chain onto the builder.
- **Authentication drivers** — `bearer`, `basic`, `header` built-ins, plus any
  FQCN implementing `Sanchescom\Rest\Auth\AuthInterface`. Configured under an
  `auth` block in the client config. Unknown drivers or missing required keys
  throw `InvalidArgumentException` at construction time.
- **Retry middleware** — `retry` block in client config: `times`, `delay` (ms),
  `multiplier`, `statuses`, `respect_retry_after`. `ConnectException` is always
  retried.
- **`Rest::fake()`** — swaps the HTTP layer with a `FakeClient`; pattern map
  uses `Str::is()` (wildcards). `Rest::response()` builds stub responses.
  Assertion helpers: `Rest::assertSent()`, `Rest::assertNotSent()`,
  `Rest::assertSentCount()`. `Rest::recorded()` returns all
  `RecordedRequest` objects (accessors: `method()`, `uri()`, `query()`,
  `data()`). Restore with `Rest::restore()` in teardown.
- **Model events** — `creating`, `created`, `updating`, `updated`, `deleting`,
  `deleted` hooks via static `Post::creating(callable)` … `Post::deleted()`.
  Returning `false` from `creating`/`updating`/`deleting` cancels the
  operation. Laravel dispatcher bridge: `ModelCreated`, `ModelUpdated`,
  `ModelDeleted` events dispatched when a dispatcher is set via
  `Model::setEventDispatcher()`.
- **`docs/capabilities.md`** — capability matrix with live-verified findings.

### Changed

- **BC for `Builder::post()` and `Builder::put()`:** return type is now
  `?Model` (was `Model`). Both return `null` when the `creating` /
  `updating` hook cancels the operation.
- **BC for custom `ClientResolverInterface` implementations:** the interface
  gains a `grammar(?string $name = null): ?string` method. Custom resolvers
  must implement it (return `null` to fall back to `PlainGrammar`).

## 1.0.0

### Added

- Typed exception hierarchy: 404 → `ModelNotFoundException`, 422 →
  `ValidationException` (with `errors()`), 5xx → `ServerException`, other
  4xx → `RequestException`; all expose `uri`, `status`, `body`.
- `Collection::paginate()` — in-memory `LengthAwarePaginator`.
- Options-aware `ClientManager` cache: clients are cached per
  name + options hash, so per-request options never leak between consumers.
- `ClientManager::extend()` for custom drivers by client or provider name.
- Config publishing via `php artisan vendor:publish --tag=rest-config`.
- Package auto-discovery — no manual provider registration.
- Full Pest test suite (unit + Testbench feature tests), PHPStan level 6,
  Laravel Pint, GitHub Actions CI matrix.

### Changed

- **BC:** PHP floor raised to 8.2; Laravel components 11/12 (individual
  `illuminate/*` packages instead of a `laravel/framework` pin).
- **BC:** clients are stateless — the endpoint/URI is passed per request;
  `setEndpoint()` is gone.
- **BC:** client methods return PSR-7 `ResponseInterface`.
- **BC:** every 4xx/5xx response throws instead of returning null/empty.
- `getMany()` results preserve the order of the input ids.
- PSR-4 layout fixed: `Sanchescom\Rest\` maps to `src/` (the old `src/Rest/`
  tree broke autoloading).
- License clarified as MIT everywhere (composer.json previously said GPL-3.0).

### Removed

- **BC:** `jenssegers/model` dependency — `Model` now ships its own attribute
  layer (`$fillable`, `$casts`, array access, JSON serialization).
- **BC:** `sanchescom/json-helper` dependency — replaced by an internal
  `Json::decode()` with strict error handling.
- Lumen support.
