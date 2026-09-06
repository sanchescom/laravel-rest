# Laravel Rest — Response Caching Design (1.2)

**Date:** 2026-09-06
**Research basis:** `docs/superpowers/specs/2026-09-05-caching-research.md` (GeneaLabs/laravel-model-caching analysis)
**Target scenario:** `Post::page(1)->get()` → `page(2)` → back to `page(1)` — third call served from cache with zero HTTP; any parameter change produces a distinct cache entry.

## Goal

Application-level caching of GET responses keyed by the compiled query, invalidated by model writes, working both in Laravel and standalone, released as 1.2.0.

## Non-Goals

- HTTP-level ETag/Cache-Control caching (future Guzzle middleware; capability matrix note).
- Cache tags (PSR-16 has no tag API) — key-prefix versioning is the single invalidation mechanism on every store.
- Cooldown/invalidation-storm throttling (REST write rates don't need it in 1.x).
- Per-client cache stores (one global store per process in 1.2; per-client is a documented limitation).

## Decisions (locked)

1. **Hook point: `CachingClient` decorator** implementing `ClientInterface`, wrapping any inner client. Intercepts `get`/`getMany`; `post/put/delete` pass through. Stores `['status', 'body']` (raw string body — no PSR-7 stream serialization issues); reconstructs `GuzzleHttp\Psr7\Response` on hit. Errors are never cached (inner client throws on ≥400 before store).
2. **Key formula:** `rest:cache:md5(clientName|modelClass|version|uri|serialize(query))`, where `version` is a per-model-class integer at `rest:v:md5(modelClass)`. Page/filter/sort flow through `Grammar::compile()` into `query`, so distinct parameters give distinct keys automatically. Key/version construction lives in `src/Cache/CacheKeys` (static helper) shared by client and flush.
3. **Invalidation: key-prefix versioning only** (deviation from research's tags-with-fallback: PSR-16 is the contract and has no tags — versioning works identically on every store). `flushCache()` increments the version; orphaned entries expire by TTL. Write-through: `Builder::post/put/delete` success paths call `flushCache()` on the model class.
4. **Contract: PSR-16 `Psr\SimpleCache\CacheInterface`.** `psr/simple-cache` goes to `suggest` (+ `require-dev` for our tests). Laravel's `Illuminate\Cache\Repository` already implements PSR-16 — the provider passes it directly, no adapter.
5. **Wiring:** static `Model::setCacheStore(?CacheInterface $store, int $defaultTtl = 300)`. The Builder wraps the resolved client in `CachingClient` when caching is active for the chain. Caching **opt-in**: model `protected ?int $cacheTtl = null` (int = on by default for that model) or per-chain `withCache(?int $ttl = null)` (ttl ?? model ttl ?? default ttl); `withoutCache()` disables the chain. Caching requested with no store configured → `RestException` (fail loud, never silently uncached — the laravel-model-caching footgun).
6. **`getMany`:** each URI keyed independently (empty query component); partial hits supported — missing URIs fetched through the inner client's `getMany`, results composed in input order.
7. **Laravel bridge:** config block `'cache' => ['store' => null /* default store */, 'ttl' => 300]` at the top level of `config/rest.php`; provider boot resolves the store and calls `Model::setCacheStore()`. Publishable stub updated (commented out by default).
8. **BC:** purely additive. `Rest::fake()` composes naturally: the fake client gets wrapped like any other, so `assertSentCount` proves cache hits in tests.

## API

```php
class Post extends Model
{
    protected ?int $cacheTtl = 300;   // opt-in per model
}

Post::page(1)->get();                 // miss -> HTTP -> cached
Post::page(1)->get();                 // hit  -> no HTTP
Post::withoutCache()->get();          // bypass chain
Post::withCache(600)->get();          // opt-in/override chain
Post::flushCache();                   // bump version, all Post entries orphaned
// standalone:
Model::setCacheStore($psr16, defaultTtl: 300);
```

## Verification

- Unit: `CachingClient` against a tiny PSR-16 array double (`tests/Support/ArrayCache`) — hit/miss/ttl passthrough, key distinctness (query/page/model/client/version), getMany partial hits, error-not-cached, post/put/delete passthrough.
- Unit: Builder/Model integration through `Rest::fake()` — page-flip scenario asserted via `assertSentCount`, `withoutCache`, `withCache`, missing-store exception, write-through invalidation.
- Feature (Testbench): array store from config, cache hit + flush end-to-end.
- Docs: README section, capability matrix rows (supported: PSR-16 GET caching, versioned invalidation; unsupported: ETag/Cache-Control, per-client stores, tags), CHANGELOG 1.2.0, UPGRADE note (none breaking).

## Task Breakdown

1. **C1** — `CacheKeys` + `CachingClient` + `ArrayCache` test double (+ `psr/simple-cache` in require-dev/suggest)
2. **C2** — Model/Builder integration (`$cacheTtl`, `setCacheStore`, `getClientName`, `flushCache`, `withCache`/`withoutCache`, Builder wrapping)
3. **C3** — Laravel provider bridge + config stub + Testbench feature test
4. **C4** — write-through invalidation in Builder write paths
5. **C5** — docs + CHANGELOG (release tag separate, after user confirmation)
