# Live Verification

Generated 2026-09-15 17:47 UTC from `1.6.0-6-g712ccac` by `composer live:catalog && composer live:report`. Do not edit by hand.

3 APIs, 44 scenarios: ✅ 41 passed · ❌ 0 failed · ⏭ 0 skipped (API unavailable) · 🚫 3 unsupported.

## Feature coverage

A feature counts as confirmed on an API when at least one of its scenarios passed there. Fewer than 3 APIs is marked ⚠️.

| Feature | Description | Confirmed on | ✅ APIs | ❌ | ⏭ | 🚫 |
| --- | --- | --- | --- | --- | --- | --- |
| `read.list` | List a collection | ⚠️ 2 | JSONPlaceholder, PokéAPI |  |  |  |
| `read.data-key` | Read items under a data key | ⚠️ 1 | PokéAPI |  |  |  |
| `read.data-key.nested` | Read items under a nested (dot) data key | ⚠️ 1 | httpbin |  |  |  |
| `read.find` | Find one model by key | ⚠️ 2 | JSONPlaceholder, PokéAPI |  |  |  |
| `read.get-many` | Fetch several models concurrently (getMany) | ⚠️ 2 | JSONPlaceholder, PokéAPI |  |  |  |
| `query.filter` | Filter on the server (where) | ⚠️ 1 | JSONPlaceholder |  |  | PokéAPI |
| `query.where-in` | Membership filter (whereIn) | ⚠️ 0 |  |  |  | JSONPlaceholder |
| `query.sort` | Sort on the server (orderBy) | ⚠️ 1 | JSONPlaceholder |  |  | PokéAPI |
| `grammar.plain` | Plain grammar | ⚠️ 1 | PokéAPI |  |  |  |
| `grammar.jsonapi` | JSON:API grammar | ⚠️ 0 |  |  |  |  |
| `grammar.django` | Django grammar | ⚠️ 0 |  |  |  |  |
| `grammar.configurable` | Configurable grammar (names, sort styles, casing) | ⚠️ 1 | JSONPlaceholder |  |  |  |
| `paginate.total` | paginate() with a response total | ⚠️ 1 | PokéAPI |  |  | JSONPlaceholder |
| `paginate.simple` | simplePaginate() | ⚠️ 2 | JSONPlaceholder, PokéAPI |  |  |  |
| `paginate.lazy` | lazy() walk across pages | ⚠️ 2 | JSONPlaceholder, PokéAPI |  |  |  |
| `paginate.style.page` | Page-number pagination style | ⚠️ 1 | JSONPlaceholder |  |  |  |
| `paginate.style.offset` | Offset pagination style | ⚠️ 1 | PokéAPI |  |  |  |
| `relation.belongs-to` | belongsTo | ⚠️ 1 | JSONPlaceholder |  |  |  |
| `relation.has-many` | hasMany by foreign key | ⚠️ 1 | JSONPlaceholder |  |  |  |
| `relation.nested` | Nested URL relation | ⚠️ 1 | JSONPlaceholder |  |  |  |
| `eager.concurrent` | Eager loading, concurrent | ⚠️ 1 | JSONPlaceholder |  |  |  |
| `eager.batch` | Eager loading, batched whereIn | ⚠️ 0 |  |  |  | JSONPlaceholder |
| `write.create` | Create (POST) | ⚠️ 2 | httpbin, JSONPlaceholder |  |  |  |
| `write.update` | Update (PUT) | ⚠️ 2 | httpbin, JSONPlaceholder |  |  |  |
| `write.patch` | Update (PATCH) | ⚠️ 2 | httpbin, JSONPlaceholder |  |  |  |
| `write.delete` | Delete | ⚠️ 2 | httpbin, JSONPlaceholder |  |  |  |
| `write.envelope` | Request data envelope (requestDataKey) | ⚠️ 1 | httpbin |  |  |  |
| `errors.not-found` | 404 → ModelNotFoundException | 3 | httpbin, JSONPlaceholder, PokéAPI |  |  |  |
| `errors.validation` | 422 → ValidationException | ⚠️ 1 | httpbin |  |  |  |
| `errors.client` | Other 4xx → RequestException | ⚠️ 1 | httpbin |  |  |  |
| `errors.server` | 5xx → ServerException | ⚠️ 1 | httpbin |  |  |  |
| `auth.bearer` | Bearer auth | ⚠️ 1 | httpbin |  |  |  |
| `auth.basic` | Basic auth | ⚠️ 1 | httpbin |  |  |  |
| `auth.header` | Header auth | ⚠️ 1 | httpbin |  |  |  |
| `headers.dynamic` | Dynamic headers (withHeaders) | ⚠️ 1 | httpbin |  |  |  |
| `retry.status` | Retry on retryable status | ⚠️ 1 | httpbin |  |  |  |
| `cache.response` | Response cache | ⚠️ 2 | httpbin, JSONPlaceholder |  |  |  |
| `cache.memo` | Request memoization | ⚠️ 1 | JSONPlaceholder |  |  |  |

## APIs

| API | Base URI | Structure | ✅ | ❌ | ⏭ | 🚫 |
| --- | --- | --- | --- | --- | --- | --- |
| httpbin | https://httpbin.org/ | kind: echo / test service<br>response: request echo objects<br>errors: any status via /status/{code}<br>auth: bearer and basic test endpoints | 15 | 0 | 0 | 0 |
| JSONPlaceholder | https://jsonplaceholder.typicode.com/ | engine: json-server<br>response: bare array / bare object<br>pagination: _page + _limit; total only in X-Total-Count and Link headers<br>filters: field=value; repeated params for OR<br>sort: _sort + _order<br>keys: integer id<br>relations: foreign-key filters and nested URLs<br>writes: faked, not persisted | 19 | 0 | 0 | 2 |
| PokéAPI | https://pokeapi.co/api/v2/ | response: results envelope with count / next / previous<br>pagination: offset + limit, absolute next URL<br>keys: name or integer id in the path<br>filters: none on list endpoints<br>detail: large nested objects | 7 | 0 | 0 | 1 |

## Failures

_None._

## Limitations

- **JSONPlaceholder › paginate with total** — Total is only exposed in the X-Total-Count header; paginate() reads totals from the response body.
- **JSONPlaceholder › membership filters** — json-server expects repeated params (id=1&id=2); whereIn renders a comma list or an indexed array (id[0]=1).
- **PokéAPI › list filters** — PokéAPI list endpoints accept only offset and limit.

## Skipped (API unavailable during the run)

_None._
