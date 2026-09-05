# Changelog

All notable changes to this project will be documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/) and this
project adheres to [Semantic Versioning](https://semver.org/).

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
