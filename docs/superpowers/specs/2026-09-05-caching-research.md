# Response Caching — Design Brief

**Date:** 2026-09-05
**Context:** laravel-rest 1.1.x, post-Phase-4 feature addition
**Target scenario:** `Post::page(1)->get()` → `Post::page(2)->get()` → `Post::page(1)->get()` — third call must be a cache hit; any parameter change = distinct cache entry.

---

## 1. Source-Code Grounding

The current architecture (read from git HEAD `f1b5f07`):

- `Builder` holds a `QueryState` (wheres, orders, limit, offset, page, extra) compiled by a `Grammar` to a `array<string,mixed>` query map. Terminal read methods: `get(?id)`, `getMany(array $ids)`, `first()`, `count()`.
- `Builder` calls `$this->model->getClient()` which delegates to `static::$resolver->client($name, $options)`. The resolver is a `ClientResolverInterface` implemented by `ClientManager`.
- `ClientInterface` has five methods: `get`, `getMany`, `post`, `put`, `delete`. It returns PSR-7 `ResponseInterface`.
- `GuzzleClient` is stateless; body is consumed once via `(string) $response->getBody()` in `Builder::decode()`. A PSR-7 body stream is read-to-EOF, so a cached `ResponseInterface` would need body rewound before re-use or the body content serialized separately.
- `Model` has no cache property today. The event hook system (static registry, `false`-cancels writes) is already the pattern for write-side invalidation hooks.
- `ClientManager` holds per-name config under `rest.clients.{name}` — a natural place for a `cache` sub-key.

---

## 2. laravel-model-caching Analysis (GeneaLabs/laravel-model-caching)

### How it works
- Adds a `Cacheable` trait to Eloquent models. The trait overrides `newEloquentBuilder()` to return a custom `CachedBuilder` subclassing Eloquent's `Builder`.
- **Cache key construction:** SHA256/MD5 of a string that encodes the model class, table, all eager-load relations, all wheres, orders, groupBy, distinct, selects, and raw query bindings — everything that changes the SQL result. Optionally prefixed with the model's `cacheCooldownSeconds` key.
- **Cache tags:** every read is tagged with the model's fully-qualified class name (and eager-loaded relation model names). On `create`/`update`/`delete`/`save`, the model trait fires `flushCache()` which calls `Cache::tags([FQCN])->flush()` on the tagged store.
- **Opt-out:** `Model::disableModelCaching()` (static toggle), `$query->disableCache()` on a builder instance, or a `$isCachable = false` property. Also a `cooldown` system to rate-limit invalidation storms.
- **Store requirement:** tags require a taggable driver (Redis, Memcached). File and array stores do NOT support tags → the package silently falls back to not caching in those cases, which is a footgun — it fails open instead of failing loud.

### Documented pitfalls
1. **Raw queries bypass the cache key** — `DB::raw()` or manually appended SQL is invisible to the key builder; results can be stale or wrongly shared.
2. **Tag store requirement** — file/database/array stores silently make cache a no-op; users must read the fine print.
3. **Relation eager loads bloat the cache** — a `with('comments')` variant shares no cache with the base query; each eager-load combination is a distinct entry.
4. **Cooldown complexity** — the TTL-based cooldown for invalidation was added to handle high-write tables, but introduces its own staleness window and extra cache keys.
5. **Global disable is fragile in tests** — `disableModelCaching()` is a static flag that survives across test cases unless explicitly reset.
6. **No support for chunked/cursor iteration** — those bypass the custom builder entirely.

---

## 3. HTTP-Level Caching vs Application-Level Caching

| Layer | Mechanism | Pros | Cons |
|---|---|---|---|
| HTTP (ETag / Cache-Control) | Guzzle middleware (e.g. `kevinrob/guzzle-cache-middleware`) respects `ETag`, `Last-Modified`, `Cache-Control: max-age` headers | Standards-compliant; server drives staleness; conditional GET saves bandwidth | Requires API to emit proper headers (most REST APIs do not); conditional GET still makes a network round-trip; does not help with page-flip UX (page 1 → page 2 → page 1 still makes 3 network calls, only the 3rd is a 304) | 
| Application (query-key cache) | Cache the decoded payload (or hydrated model data) keyed by query fingerprint | Zero network round-trips on cache hit; works regardless of API headers; enables page-flip UX; TTL fully under our control | Requires explicit invalidation strategy; stale data if API changes out of band |

**Recommendation:** implement application-level caching as the primary feature. HTTP-level ETag support (via a future `HttpCacheMiddleware`) is an orthogonal Guzzle concern that can be added later without touching the caching layer — note it in the capability matrix.

---

## 4. Where to Hook: `CachingClient` Decorator vs Builder-Level

### Option A — `CachingClient implements ClientInterface` (decorator wrapping any client)

```
Builder → CachingClient → GuzzleClient
```

The decorator intercepts `get()` and `getMany()`, computes a cache key from `(uri, query)`, checks the store, and on miss calls the inner client and caches the raw response body (`string`) + status code. On cache hit it reconstructs a minimal PSR-7 response from the stored string. Pass-through (no cache, no key computation) for `post/put/delete`.

Pros:
- Single responsibility: cache logic isolated in one class, zero Builder changes.
- Works for any future client implementation automatically.
- `getMany` can be handled per-URI (cache each independently) with natural key reuse across `get` and `getMany` for the same URI.
- PSR-7 rewind problem avoided: we store the body as `string`, reconstruct the response; no stream rewind needed.
- Write-through invalidation is clear: `post/put/delete` on the same model tag → flush. The decorator can accept a tag prefix, or invalidation can go via model events.

Cons:
- Cache key at client level only knows `(uri, query)` — it does NOT know the model class or `dataKey`. If two models share the same endpoint but differ only in `dataKey` post-processing, keys are still correct (same URI + query → same raw payload, both benefit from the same cache entry). No collision risk.
- Needs access to a taggable or tag-fallback cache handle at construction time.

### Option B — Builder-Level Caching of Hydrated Results

Cache the final `Model|Collection` after `hydrate()` inside `Builder::get()`.

Pros:
- Key can include model class, dataKey, client name natively.
- Can skip the PSR-7 serialization concern entirely.

Cons:
- Model/Collection serialization is non-trivial (PHP `serialize()` works but couples cache format to class structure; breaks if attributes change shape).
- `getMany` involves concurrent async Guzzle calls — wrapping each response per-URI is awkward at Builder level.
- Invalidation cannot be "flush before the HTTP call" (we'd need to know the URI at the start of `post/put/delete`, which the Builder already computes — but flushing hydrated-model caches from a write path that also lives in Builder creates coupling).
- Harder to make work in standalone (no Laravel) mode since Collections may depend on Laravel internals.

**Recommendation: Option A — `CachingClient` decorator.** It is cleaner, avoids model serialization concerns, handles `getMany` naturally, and keeps cache logic entirely outside the model/builder layer. The cache key at client level is fully sufficient for the target scenario.

---

## 5. Cache Key Design

```
rest:cache:{client_name}:{model_fqcn_hash}:{uri_hash}:{query_hash}
```

Concretely, built inside `CachingClient` (which receives `clientName` + `modelClass` at construction from the resolver):

```php
$key = 'rest:cache:' . md5(implode('|', [
    $this->clientName,     // e.g. "jsonplaceholder"
    $this->modelClass,     // e.g. "App\Models\Post"
    $uri,                  // e.g. "posts"
    serialize($query),     // compiled query array from Grammar::compile()
]));
```

- `page` is part of `QueryState` and compiled into the query array by `PlainGrammar` / `JsonApiGrammar`, so `Post::page(1)->get()` and `Post::page(2)->get()` get distinct keys automatically — no special handling.
- `dataKey` does NOT need to be in the key: two models with the same endpoint+query but different `dataKey` will share the same raw payload in the cache. Since `extract()` applies `dataKey` after decode, each model reads the same cached payload and extracts its own key. This is actually an optimization.
- For `getMany`, each URI is keyed independently using the same formula with an empty query array. Results are composed from individual cache entries, allowing partial cache hits.

**Tag:** `rest:tag:{model_fqcn_hash}` — used to flush all entries for a model class (all pages, all queries).

---

## 6. Invalidation Strategy

### Write-through via model events (already in 1.1)

The existing model event hooks (`creating/created/updating/updated/deleting/deleted`) are the natural invalidation trigger. The `CachingClient` exposes a `flushTag(string $modelClass): void` method. On `Post::post()`, `Post::put()`, `Post::delete()`, `Builder` fires the write-side events — we hook into the `*ed` (post-write) events to call `flushTag`.

Concretely, add a `static::flushesCacheFor(string $modelClass)` registration in the `RestServiceProvider` boot, or simply: `Builder::post/put/delete` call `$this->model->flushCache()` after a successful write. This is explicit and works standalone.

### Manual flush

```php
Post::flushCache();  // flushes all cache entries tagged with Post::class
```

Implemented as a static method on `Model` that delegates to the resolver/cache handle.

### TTL

Default: `300` seconds (5 minutes), configurable per client in `config/rest.php` and overridable per model.

### Stores without tag support (File, Database, Array)

Tag-based flush is not available. Fallback strategy: **key-prefix versioning**.

Maintain a version counter in cache: `rest:v:{model_fqcn_hash}` → integer. Include the version in every cache key. `flushCache()` increments the version (cheap atomic write). Old keys become orphaned (they expire via TTL naturally). No tag store required.

```php
$version = $this->cache->get("rest:v:{$modelHash}", 0);
$key = "rest:cache:{$this->clientName}:{$modelHash}:{$version}:{$uriHash}:{$queryHash}";
// On flush:
$this->cache->set("rest:v:{$modelHash}", $version + 1, null); // no TTL on version key
```

The `CachingClient` detects tag support by attempting to call `tags()` on the cache store (check if `TaggedCache` is returned without exception, or type-check `CacheStore`). If not taggable, silently use prefix versioning.

---

## 7. PSR-16 SimpleCache as the Dependency Contract

**Rationale:** `psr/simple-cache` (`CacheInterface`) is the minimal, framework-agnostic cache contract. It is already a transitive dependency of `illuminate/cache` (which is itself a transitive dependency of `illuminate/support`) — so in Laravel contexts it costs nothing. In standalone contexts a user can wire in any PSR-16 adapter (Symfony Cache, Stash, etc.).

**Dependency declaration:** add `"psr/simple-cache": "^2.0|^3.0"` as a `suggest` in `composer.json` with a note that it is required only when caching is enabled. Keep it out of `require` so the package keeps working without it for users who do not use caching.

**Exception:** when `$cacheTtl` is set but no `CacheInterface` is injected, throw a descriptive `\RuntimeException` at build time (not silently no-op).

**Laravel bridge:** `RestServiceProvider::boot()` resolves `cache.store` (or `rest.clients.{name}.cache.store`) and wraps it in a PSR-16 adapter via `Illuminate\Cache\Psr16Adapter` before passing to `CachingClient`. No new dependencies.

---

## 8. API Sketch

### Model properties

```php
class Post extends Model
{
    // false = no caching; int = TTL in seconds; null = use client config default
    protected int|false|null $cacheTtl = null;
}
```

### Builder opt-out

```php
Post::withoutCache()->page(1)->get();   // bypasses cache for this query chain
Post::withCache(600)->page(1)->get();   // override TTL for this chain
```

`withoutCache()` / `withCache()` set a flag on the Builder that `CachingClient` reads (passed via a context envelope or a per-request decorator bypass).

### Manual flush

```php
Post::flushCache();                     // flush all Post cache entries
Post::flushCache('posts/1');           // flush a specific URI (optional, advanced)
```

### Config

```php
// config/rest.php
'clients' => [
    'jsonplaceholder' => [
        'base_uri' => 'https://jsonplaceholder.typicode.com',
        'cache' => [
            'store'  => 'redis',   // or null → default Laravel cache store
            'ttl'    => 300,       // seconds; false to disable caching for this client
        ],
    ],
],
```

### Standalone wiring

```php
$cache = new \Symfony\Component\Cache\Psr16Cache(...);
$cachingClient = new CachingClient(
    inner: $guzzleClient,
    cache: $cache,
    clientName: 'my-api',
    modelClass: Post::class,
    ttl: 300,
);
Model::setClientResolver(new SimpleResolver($cachingClient));
```

---

## 9. What Transfers from laravel-model-caching — and What Does Not

### Transfers well
- **Tag-per-model-class invalidation** — the core insight (tag = model FQCN, flush all on write) maps directly to REST; we keep it.
- **Query fingerprint key** — their MD5-of-serialized-query approach is exactly right for our `Grammar::compile()` output.
- **Per-model opt-in/opt-out** — their `$isCachable` / `disableModelCaching()` pattern translates to `$cacheTtl = false` / `withoutCache()`.
- **Fallback for unsupported stores** — they silently no-op; we do better with key-prefix versioning.

### Does NOT transfer
- **SQL-level key encoding** (table, bindings, raw SQL, eager loads) — irrelevant; we have no SQL. Our Grammar output is already the canonical, complete query representation.
- **`CachedBuilder` extends `EloquentBuilder`** — they subclass Eloquent; we use a decorator on `ClientInterface`. Cleaner for our use case.
- **Cooldown system** — their cooldown is needed for high-write Eloquent tables to prevent invalidation storms. REST write rates are far lower and controlled by the caller, so a simple TTL + explicit flush is sufficient for 1.x. Cooldown can be revisited in 2.x if demanded.
- **Relation-aware cache key** — they include eager-load model names in keys. We do not have an eager-load system in 1.1 (relations are lazy-resolved per model instance), so no relation key component needed yet.
- **`Cache::tags()->flush()` direct usage** — they depend on `Illuminate\Cache` directly. We use PSR-16 + fallback versioning to stay standalone-compatible.

### Pitfalls to avoid (from their documented issues)
- **Silent no-op on non-taggable stores** → we use key-prefix versioning as explicit fallback.
- **Static disable flag leaking between tests** → our `withoutCache()` is per-Builder-instance (immutable flag on state), not a global static.
- **Footgun: no cache = no error** → when `$cacheTtl` is set but no cache backend is configured, we raise an exception rather than silently running uncached.
- **Raw query bypass** → not applicable; all query parameters flow through `Grammar::compile()` which we fully control.

---

## 10. Rough Task Breakdown

**Task C-1: `CachingClient` decorator + key/tag infrastructure**
- Implement `src/Cache/CachingClient.php` (implements `ClientInterface`).
- Cache key builder (client name + model class + URI + query hash).
- Tag support detection + key-prefix versioning fallback.
- Intercept `get`/`getMany` for cache read/write; pass-through for `post/put/delete`.
- PSR-16 `CacheInterface` dependency; `RuntimeException` when not provided but TTL is set.
- Unit tests with an array PSR-16 adapter (no Laravel needed).

**Task C-2: Model integration — `$cacheTtl`, `withoutCache()`, `withCache()`, `flushCache()`**
- Add `protected int|false|null $cacheTtl` to `Model`.
- Add `Builder::withoutCache()` / `withCache(int $ttl)` returning `$this` (fluent).
- Add `Model::flushCache(string $uri = null): void` static method.
- Wire `Builder` to construct `CachingClient` wrapping the real client when `$cacheTtl` is active and a cache handle is available.
- Tests: cache hit on repeated `page(1)` call; distinct key for `page(2)`; `withoutCache()` bypasses; `flushCache()` invalidates.

**Task C-3: Laravel provider bridge**
- `RestServiceProvider` reads `rest.clients.{name}.cache` config.
- Resolves store via `CacheManager`, wraps in `Psr16Adapter`, passes to `CachingClient` factory in `ClientFactory`.
- Publishes updated `config/rest.php` stub with the `cache` sub-key (commented out by default).
- Integration test: boot Testbench app with `array` cache store, verify cache hit and flush.

**Task C-4: Invalidation via model events**
- Hook `Builder::post/put/delete` success path to call `$this->model->flushCache()`.
- Tests: `post()` → subsequent `get()` is a fresh API call (cache invalidated).
- Document: write-through is best-effort — direct API mutations outside the package cannot be detected.

**Task C-5: Documentation + capability matrix entry**
- Add caching section to README: configuration, `$cacheTtl`, `withoutCache()`, `flushCache()`, store requirements.
- Add to `docs/capabilities.md`: "Application-level GET caching (PSR-16) — supported; HTTP ETag/Cache-Control caching — not supported (planned)."
- Update CHANGELOG.

---

## 11. Constraints Checklist

| Constraint | Status |
|---|---|
| No new hard runtime deps | `psr/simple-cache` as `suggest` only — pass |
| Standalone (no Laravel) | `CachingClient` depends only on `psr/simple-cache`; provider is opt-in — pass |
| PHP 8.2 | `int\|false\|null` union type, constructor promotion, readonly — all 8.2 — pass |
| BC with 1.x API | `Model` gains a property + static methods; `Builder` gains fluent opt-out methods — additive only — pass |
| No `dd`/`dump`, no `env()` outside config | trivially enforced — pass |
