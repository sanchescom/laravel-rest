# Roadmap

Priorities are driven by three sources: the [capability matrix](docs/capabilities.md)
(what is honestly marked unsupported), ideas deferred during design discussions,
and review findings parked across the 1.0–1.3 release cycles. Order within a
release is indicative, not binding.

## 1.4 — Complete Reads

The features you hit on day one of consuming a real API.

- **Server-side pagination with metadata.** `paginateRemote(int $perPage)`
  returning a real `LengthAwarePaginator` built from response metadata, with
  configurable keys in the conventions style
  (`'pagination' => ['total' => 'meta.total', 'last_page' => 'meta.last_page']`).
  Cursor/Link-header pagination as a follow-up layer.
- **Eager loading.** `Post::with('comments')->get()` — collect parent keys,
  batch the relation via a single `whereIn` query when the API supports it,
  falling back to concurrent `getMany` by id; distribute onto instances.
  Eliminates the HTTP N+1 that lazy relations currently produce.
- **Per-request identity map.** Repeated `find()` of the same key within one
  application request (typically `belongsTo` across a collection) hits HTTP
  once. Scoped to the request lifecycle, never shared across requests.
- **Lazy iteration.** `Post::lazy()` walks every page on top of the
  pagination metadata above; also the building block for syncing API data
  into local tables (documented recipe, no dedicated converter).
- **Explicit attribute mapping.** `protected array $attributeMap = ['createdAt' => 'created_at']`
  — dictionary-based bidirectional renaming (read hydration and write bodies),
  deliberately not automatic casing conversion (rejected in the 1.3 design:
  snake↔camel is not bijective).
- **Fake sequences.** `Rest::fakeSequence('posts', [...])` — different
  responses for consecutive matching requests; needed to test retry and
  polling flows.

## 1.5 — Transport and Integrations

Widening the set of APIs the package can talk to.

- **Multipart and form-encoded bodies** (file uploads; per-client
  `'body_format'` or a dedicated multipart call). Currently the hardest
  "unsupported" row in the capability matrix.
- **OAuth2 auth driver** — client_credentials and refresh flows with token
  caching (PSR-16 store already exists) and automatic refresh on 401.
- **HTTP-level caching** — ETag / Cache-Control conditional GET as a Guzzle
  middleware, orthogonal to the application-level cache.
- **Retry polish** — jitter, HTTP-date `Retry-After` support (documented
  limitation today), optional circuit breaker.
- **Concurrent requests.** An async primitive on the client (promise for any
  request, not just GET-by-URI) powering `Rest::pool(fn ($pool) => [...])`
  and concurrent loading of several relations in one `with([...])`. Built on
  Guzzle's `curl_multi`; no ReactPHP/Amp — they add nothing under PHP-FPM.
- **Stale-while-revalidate.** Serve expired cache entries immediately and
  refresh them after the response (`defer()`) or via a queued job; pairs
  with scheduled cache warming for hot endpoints.
- **Sparse fieldsets.** `select('id', 'title')` rendered by the grammar
  (e.g. JSON:API `fields[posts]=id,title`) to shrink payloads.
- **Observability** — `RequestSending` / `ResponseReceived` events through the
  dispatcher plus configurable slow-request/error logging.

## 2.0 — Foundation Cleanup (breaking changes allowed)

- **Raise floors: PHP 8.3+, Laravel 12+.** Laravel 11 is EOL; CI keeps it
  alive only by disabling composer advisory blocking.
- **Consolidate `ClientResolverInterface`.** It grew a method per minor
  release (`grammar()`, `queryConfig()`); replace with a context/config DTO so
  future extensions stop being BC notes.
- **Accumulated small debt:** `switch` → `match` in `ConfigurableGrammar`;
  builder chaining on `HasOne` (documented asymmetry with `HasMany`); dead
  branches in `Builder::first()/count()`; `getMany` recordings
  indistinguishable from `get` in fakes.

## Ongoing (no release attached)

- **Performance docs:** HTTP/2 and compression via client `options`
  (`'version' => 2.0`, `decode_content`); recipe for syncing REST models into
  Eloquent tables (`lazy()` + `upsert`), with a `rest:sync` command only if
  real demand appears.
- **DX:** `php artisan make:rest-model User --client=crm` generator; more
  grammar presets (e.g. Spring, Stripe-style).
- **CI:** nightly job for the `live` test group (currently run manually before
  releases).
- **Visibility:** GitHub topics, richer package description, optionally a docs
  site generated from the markdown in `docs/`.

## Release History

| Version | Theme |
| --- | --- |
| 1.0.0 | Modernization: PHP 8.2, Laravel 11/12, typed exceptions, stateless clients, full test suite |
| 1.1.0 | Features: query builder + grammars, relations, auth, retry, fakes, events |
| 1.2.0 | Response caching: PSR-16, versioned invalidation, write-through |
| 1.3.0 | API conventions: configurable names/sorts/filters, presets, dynamic headers, error keys, PATCH, envelopes |
