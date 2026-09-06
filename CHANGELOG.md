# Changelog

All notable changes to this project will be documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/) and this
project adheres to [Semantic Versioning](https://semver.org/).

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
