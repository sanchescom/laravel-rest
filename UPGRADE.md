# Upgrade Guide

## 1.1 → 1.2

1.2 is fully additive — no breaking changes. To enable caching, add `$cacheTtl`
to any model and ensure `config/rest.php` has a `cache` block (it ships with
`['store' => null, 'ttl' => 300]` by default).

## 1.0 → 1.1

### `Builder::post()` and `Builder::put()` return `?Model`

Previously both methods always returned a `Model`. They now return `?Model`:
they return `null` when a `creating` or `updating` event listener cancels the
operation by returning `false`. If you call these methods via the static
proxies (`Post::post(...)`, `Post::put(...)`) and do not register any event
listeners, nothing changes.

If you have type-checked call sites (`Model $result = Post::post([...]);`),
update them to accept `null`:

```php
// before
$post = Post::post(['title' => 'Hello']); // Model

// after
$post = Post::post(['title' => 'Hello']); // ?Model — null if cancelled
```

### Custom `ClientResolverInterface` implementations

`ClientResolverInterface` gains one new method:

```php
public function grammar(?string $name = null): ?string;
```

Any class that implements this interface (typically a custom resolver wired
outside Laravel) must add this method. Return `null` to fall back to
`PlainGrammar`. The two built-in implementations (`ClientManager` and
`ClientResolver`) already implement it.

---

## 0.x → 1.0

1.0 is a rewrite. The public model API (`get`, `getMany`, `post`, `put`,
`delete`) is source-compatible for typical usage, but the underpinnings
changed in breaking ways.

### Requirements

- PHP **8.2+** (was 7.2).
- Laravel **11 or 12** (was 5.8). The package now depends on individual
  `illuminate/*` components, not `laravel/framework`.

### Model

- `Sanchescom\Rest\Model` no longer extends `Jenssegers\Model\Model`; it ships
  its own attribute layer. `$fillable` and `$casts` keep working; anything
  relying on jenssegers internals must be revisited.
- Subclass property declarations now need types:

  ```php
  // before
  protected $dataKey = 'data';
  protected $endpoint = 'users';

  // after
  protected ?string $dataKey = 'data';
  protected ?string $endpoint = 'users';
  protected array $fillable = [...];
  ```

### Clients

- `ClientInterface` changed: the URI is passed per call and clients are
  stateless — `setEndpoint()` no longer exists. Custom client implementations
  must implement `get(string $uri, array $query = [])`, `getMany(array $uris)`,
  `post(string $uri, array $data = [])`, `put(string $uri, array $data = [])`,
  `delete(string $uri)`.
- Client methods return PSR-7 `Psr\Http\Message\ResponseInterface` instead of
  `Illuminate\Http\Response`.
- `base_uri` in config **must end with a trailing slash**; endpoints must not
  start with one.

### Error handling

- Every 4xx/5xx response now throws
  (`ModelNotFoundException` / `ValidationException` / `ServerException` /
  `RequestException`). Code that checked for `null` or empty results after a
  failed call must catch exceptions instead.
- `put()`/`delete()` without an id **and** without a primary key value on the
  instance throw `RestException` instead of silently hitting the collection
  endpoint.

### License

- The license is MIT everywhere now (composer.json previously declared
  GPL-3.0 by mistake).
