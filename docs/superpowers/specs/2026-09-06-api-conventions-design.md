# Laravel Rest — API Conventions / Universality Design (1.3)

**Date:** 2026-09-06
**Target release:** 1.3.0
**Approved by user:** config-arrays-first UX; four feature blocks; query-only casing (attribute casing explicitly rejected — see Decisions).

## Goal

Make the library adapt to differing REST API conventions declaratively: query parameter names and formats, sort/filter styles, dynamic headers, validation-error shapes, PUT vs PATCH, request body envelopes — via config arrays with string presets, keeping classes only for true exotics.

## Non-Goals

- Attribute-key casing conversion (rejected: irreversible snake↔camel mappings, cascades into fillable/casts/relations/fakes; an explicit per-model `$attributeMap` is a 1.4 candidate if a real API demands it).
- Model-level update-method override (client-level only; YAGNI).
- OData/GraphQL query languages (custom Grammar remains the escape hatch).
- Response-side pagination metadata parsing.

## Decisions (locked)

1. **UX pattern: config arrays + presets, classes for exotics** — continues the 1.x pattern (`auth`, `retry`, `cache` are arrays; `Grammar`/`AuthInterface` are the class seams). Closures rejected (config:cache); profile classes rejected (friction, god-object risk).
2. **`ConfigurableGrammar implements Grammar`** consumes a `'query'` array from client config. Grammar resolution order gains one step: model `$grammar` FQCN → client `'grammar'` FQCN → client `'query'` array/string → `PlainGrammar`. Existing grammar classes untouched.
3. **Config schema** (all keys optional; unknown keys/values → `InvalidArgumentException` at construction — never silent):
   - `'preset'` (or the whole block as a string): `'jsonapi'` | `'django'`. Preset = named defaults array; explicit keys override preset keys. Preset contents: `jsonapi` = `['filters' => 'brackets', 'sort' => 'dash', 'names' => ['limit' => 'page.size', 'page' => 'page.number', 'offset' => 'page.offset']]` (byte-equal output with `JsonApiGrammar`); `django` (DRF conventions) = `['filters' => 'django', 'sort' => 'dash', 'names' => ['sort' => 'ordering', 'limit' => 'page_size']]`.
   - `'names'`: rename any emitted parameter — `['limit' => 'per_page', 'offset' => 'skip', 'page' => 'p', 'sort' => 'order_by']`. Dot notation nests: `'limit' => 'page.size'` emits `page[size]=...` — this is how the jsonapi preset expresses `page[size]`/`page[number]`.
   - `'sort'`: `'dash'` (default; `sort=-date,name`) | `'separate'` (`order_by=date&dir=desc`, first order only) | `'suffix'` (`sort=date:desc,name:asc`) | `'array'` (`sort[date]=desc`).
   - `'sort_names'`: for `separate` — `['field' => 'order_by', 'direction' => 'dir']`.
   - `'sort_suffix'`: separator for `suffix` style (default `':'`).
   - `'filters'`: `'plain'` (default; `field=v`, `field[op]=v`) | `'brackets'` (`filter[field]=v`, `filter[field][op]=v`) | `'django'` (`field=v`, `field__op=v`).
   - `'casing'`: `null` | `'snake'` | `'camel'` — applied to FIELD names in filters and sort only (query side is write-only, hence safe).
   - Pagination naming via `'names'` (jsonapi preset maps limit/page to `page[size]`/`page[number]` internally via its filters/brackets structure — preset `jsonapi` reproduces `JsonApiGrammar` output exactly).
4. **Dynamic headers**: model `protected array $headers = []` + chain `withHeaders(array $headers): self` (merge, chain wins). Plumbing: `Model::getClient(array $extraOptions = [])` (additive param) → resolver options; `ClientManager` already caches per name+options hash. **Cache-correctness rule:** dynamic headers MUST discriminate the cache key — `CachingClient` gains optional `?string $keyExtra = null` ctor param; Builder passes `md5(serialize($headers))` when headers are present. Standalone `ClientResolver` ignores options — documented limitation.
5. **Errors key**: client config `'errors_key' => 'error.details'` (dot notation, default `'errors'`). `GuzzleClient::fromConfig` reads it and passes through `ensureSuccessful` → `RequestException::fromStatus(string $uri, int $status, array $body = [], ?string $errorsKey = null)` (additive optional param) → `ValidationException::errors()` uses `Arr::get($this->body, $this->errorsKey ?? 'errors', [])`.
6. **Update verb**: client config `'update_method' => 'put'|'patch'` (default `'put'`). `ClientInterface` unchanged — `put()` means "update"; `GuzzleClient` picks the wire verb from its config. Builder/fakes/custom clients unaffected; `FakeClient` keeps recording logical `PUT`.
7. **Request envelope**: model `protected ?string $requestDataKey = null`; when set, Builder wraps write payloads (`post`/`put`) as `[$requestDataKey => $attributes]`. Separate from `$dataKey` (read and write envelopes often differ). Response unwrapping unchanged.
8. **BC:** zero signature changes; two additive optional params (`fromStatus` 4th arg, `getClient` 1st arg); one additive ctor param on `CachingClient`.

## API Examples (authoritative for docs)

```php
// CRM with per_page/p naming and two-param sort:
'crm' => [
    'base_uri' => 'https://crm.example.com/api/',
    'query' => [
        'names' => ['limit' => 'per_page', 'page' => 'p'],
        'sort' => 'separate',
        'sort_names' => ['field' => 'order_by', 'direction' => 'dir'],
    ],
    'errors_key' => 'error.details',
    'update_method' => 'patch',
],
// Django-style API in one word:
'reports' => ['base_uri' => '...', 'query' => 'django'],

Order::where('status', 'active')->orderBy('createdAt', 'desc')->limit(20)->get();
// crm     -> GET orders?status=active&order_by=createdAt&dir=desc&per_page=20
// reports -> GET orders?status=active&ordering=-createdAt&page_size=20 (django preset)

Invoice::withHeaders(['Accept-Language' => 'de'])->get();

class Payment extends Model
{
    protected array $headers = ['X-Tenant-Id' => '42'];

    protected ?string $requestDataKey = 'data'; // POST {"data": {...}}
}
```

## Verification

- Unit matrix on `ConfigurableGrammar`: every sort style × filter style × names × casing × presets (+ preset-override merging, invalid-config exceptions, jsonapi preset byte-equality with `JsonApiGrammar` output).
- Fixture-server integration: renamed params on the wire (echo route), dynamic headers arrive, PATCH verb on the wire (new fixture route or echo method), errors_key with 422 route variant.
- `Rest::fake` unit tests: request envelope in `$request->data()`, headers not applicable (fake path documented).
- Caching: distinct entries for distinct dynamic headers (keyExtra test).
- Docs task must be example-rich (explicit user request): README "Adapting to API conventions" section with a worked example per config key, cookbook-style; capability matrix update; CHANGELOG 1.3.0; UPGRADE (additive).

## Task Breakdown

1. **U1** — `ConfigurableGrammar` + presets + validation (+ resolution order wiring in `Model::newBuilder` and `ClientManager`)
2. **U2** — dynamic headers (model + chain + getClient extraOptions + CachingClient keyExtra)
3. **U3** — errors_key + update_method (GuzzleClient config plumbing, additive fromStatus param)
4. **U4** — request envelope (`$requestDataKey` in Builder writes)
5. **U5** — integration sweep on fixture server (wire-level proof for U1–U4 together)
6. **U6** — docs: example-rich README section, capabilities, CHANGELOG, UPGRADE
