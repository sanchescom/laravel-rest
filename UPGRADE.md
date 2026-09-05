# Upgrade Guide

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
