# Roadmap

Priorities are driven by three sources: the [capability matrix](docs/capabilities.md)
(what is honestly marked unsupported), ideas deferred during design discussions,
and review findings parked across the 1.0–1.3 release cycles. Order within a
release is indicative, not binding.

## 1.7 — Complete Reads (continued)

The features you hit on day one of consuming a real API. Pagination shipped
in 1.4.0, lazy iteration and request memoization in 1.5.0, eager loading in
1.6.0; the rest follows.

- **Cursor / Link-header pagination.** `cursorPaginate()` as a follow-up
  layer on top of 1.4.0's `paginate()` / `simplePaginate()`.
- **Explicit attribute mapping.** `protected array $attributeMap = ['createdAt' => 'created_at']`
  — dictionary-based bidirectional renaming (read hydration and write bodies),
  deliberately not automatic casing conversion (rejected in the 1.3 design:
  snake↔camel is not bijective).
- **Fake sequences.** `Rest::fakeSequence('posts', [...])` — different
  responses for consecutive matching requests; needed to test retry and
  polling flows.

## 1.8 — Transport and Integrations

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
  `Rest::fake()` ignores client `grammar`/`query` config, so faked requests
  compile with `PlainGrammar` (fixing it changes query shapes seen by
  `assertSent`).

## Ongoing (no release attached)

- **Performance docs:** HTTP/2 and compression via client `options`
  (`'version' => 2.0`, `decode_content`); recipe for syncing REST models into
  Eloquent tables (`lazy()` + `upsert`), with a `rest:sync` command only if
  real demand appears; `defer()` for fire-and-forget writes (analytics, logs)
  but never for regular create/update, which would lose validation errors and
  created ids; connection reuse — keep-alive already works within one PHP
  request, while reuse across FPM requests needs Octane (or PHP 8.5
  persistent curl share handles).
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
| 1.4.0 | Server-side pagination: `paginate()` / `simplePaginate()` from response metadata, pagination presets |
| 1.5.0 | `lazy()` page iteration, opt-in per-request memoization of GET responses |
| 1.6.0 | Eager loading (`with()` / `load()`, concurrent or batched `whereIn`), `whereIn()` in all grammars |
