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
2. `'grammar'` key in the client config / `ClientResolver::setGrammar()` (per-client default)
3. `PlainGrammar` (package default)

Under `Rest::fake()` the config-level grammar is not honoured (the fake
resolver returns `null` for `grammar()`). The model-level `$grammar` still
applies. This is documented behaviour, acceptable for test contexts.

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
