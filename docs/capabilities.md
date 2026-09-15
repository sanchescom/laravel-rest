# Capability Matrix

What laravel-rest supports as of 1.5.0 — and what it deliberately does not.

| Area | Supported | Not supported (workaround) |
| --- | --- | --- |
| Request bodies | JSON | form-encoded, multipart, XML (custom `ClientInterface`) |
| Query filters | plain `?field=value`, JSON:API `filter[...]`, django `field__op=value`, `ConfigurableGrammar` (all three via `'query'` config), `whereIn()` (comma list or `'in' => 'array'`), custom `Grammar` | GraphQL, OData `$filter` (custom `Grammar`) |
| Sorting | 4 styles: `dash` (`-field`), `suffix` (`field:dir`), `separate` (two params), `array` (`sort[field]=dir`); renames via `names.sort`; casing via `casing` key; all configurable per client | — |
| Pagination | `paginate()` (`LengthAwarePaginator` from a response total) and `simplePaginate()` (next link, has-more flag, or full-page inference); `lazy()` iterates every page; `page` or `offset` style; presets `laravel`, `django`, `jsonapi`; param names via `names` (e.g. `page[size]`) | Link-header, cursor tokens (use `withQuery()` manually) |
| Auth | bearer, basic, arbitrary headers, custom `AuthInterface` | OAuth2 token acquisition/refresh (attach ready tokens only) |
| Retry | status-based with exponential backoff and `Retry-After` (integer seconds) | HTTP-date form of `Retry-After` (workaround: exponential backoff), circuit breakers, jitter |
| Errors | 404/422/5xx/4xx typed exceptions, JSON bodies; configurable errors key (`errors_key`, dot notation) | non-JSON error bodies are preserved as empty `body` |
| Envelopes | `dataKey` (read, dot notation via `Arr::get`); `requestDataKey` (write side, wraps POST/PUT/PATCH body) | per-endpoint different keys on one model |
| HTTP verbs | GET, POST, DELETE; PUT or PATCH for updates — configurable per client via `update_method` | — |
| Relations | hasMany/hasOne (FK filter or nested URL), belongsTo; eager loading with `with()` / `load()` — concurrent per parent, or one `whereIn` request with `batch()`; dot nesting and constraints | many-to-many, embedded includes |
| Events | creating/created/updating/updated/deleting/deleted, cancellation, Laravel bridge | wildcard observers |
| Caching | PSR-16 GET caching, versioned invalidation, per-model (`$cacheTtl`) and per-chain (`withCache()`/`withoutCache()`) opt-in; cache key discriminates on headers; opt-in per-request memoization of GET responses (`rest.memoize`), reset per Octane request and queue job | HTTP ETag/Cache-Control (planned), per-client stores, cache tags |
| Testing | `Rest::fake()` with patterns + assertions, fixture server pattern | — |

## Live Verification

Verified against: JSONPlaceholder, httpbin, job-api (internal),
OpenAI-compatible endpoints, Anthropic. Findings from those runs:

- **Header auth casing is API-specific.** FastAPI's `APIKeyHeader` emits
  `X-API-Key` (capital K). Use the exact casing the API expects — the `header`
  driver passes whatever you configure verbatim.
- **Health endpoints are often unauthenticated.** Do not use them to verify
  that auth credentials are accepted; use a protected endpoint instead.
- **OpenAI-compatible proxies** work by overriding `base_uri` in the client
  config — no other changes needed.

## Grammar Resolution Order

When building a query, the grammar is selected in this order:

1. `protected ?string $grammar` on the model class (per-model override)
2. `'grammar'` key in the client config / `ClientResolver::setGrammar()` (per-client, explicit class)
3. `'query'` key in the client config / `ClientResolver::setQueryConfig()` (per-client, `ConfigurableGrammar` — array or preset string)
4. `PlainGrammar` (package default)

Under `Rest::fake()` the inline resolver returns `null` for both `grammar()`
and `queryConfig()`, so neither config-level grammar nor `ConfigurableGrammar`
is active. The model-level `$grammar` property is still resolved (it is read
from the model directly). The standalone `ClientResolver` also ignores the
`options` argument passed to `client()` — if you need headers at the client
level, pass them directly when constructing the `ClientInterface` instance.
Both behaviours are intentional for test contexts.

## Custom Grammar

Implement `Sanchescom\Rest\Query\Grammar`:

```php
use Sanchescom\Rest\Query\Grammar;
use Sanchescom\Rest\Query\QueryState;

final class ODataGrammar implements Grammar
{
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            $query['$filter'] = "{$where['field']} eq {$where['value']}";
        }

        if ($state->limit !== null) {
            $query['$top'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['$skip'] = $state->offset;
        }

        return $query;
    }
}
```

## Custom Auth Driver

Implement `Sanchescom\Rest\Auth\AuthInterface`:

```php
use Psr\Http\Message\RequestInterface;
use Sanchescom\Rest\Auth\AuthInterface;

final class HmacAuth implements AuthInterface
{
    public function __construct(private readonly array $config) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $signature = hash_hmac('sha256', (string) $request->getUri(), $this->config['secret']);

        return $request->withHeader('X-Signature', $signature);
    }
}
```

Configure with `'driver' => HmacAuth::class` in the client's `auth` block.
