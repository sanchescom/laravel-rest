<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Cache\CachingClient;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Pagination\PaginationConfig;
use Sanchescom\Rest\Pagination\RemotePaginator;
use Sanchescom\Rest\Query\Grammar;
use Sanchescom\Rest\Query\PlainGrammar;
use Sanchescom\Rest\Query\QueryState;
use Sanchescom\Rest\Support\Json;

final class Builder
{
    private QueryState $state;

    private ?string $endpointOverride = null;

    private ?int $cacheTtlOverride = null;

    private bool $cacheDisabled = false;

    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private readonly Model $model,
        private readonly Grammar $grammar = new PlainGrammar,
    ) {
        $this->state = new QueryState;
    }

    public function where(string $field, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->state->wheres[] = ['field' => $field, 'operator' => (string) $operator, 'value' => $value];

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->state->orders[] = ['field' => $field, 'direction' => $direction];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->state->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->state->offset = $offset;

        return $this;
    }

    public function page(int $page): self
    {
        $this->state->page = $page;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function withQuery(array $params): self
    {
        $this->state->extra = array_merge($this->state->extra, $params);

        return $this;
    }

    public function from(string $endpoint): self
    {
        $this->endpointOverride = $endpoint;

        return $this;
    }

    public function withCache(?int $ttl = null): self
    {
        $this->cacheDisabled = false;
        $this->cacheTtlOverride = $ttl ?? $this->model->getCacheTtl() ?? Model::getDefaultCacheTtl();

        return $this;
    }

    public function withoutCache(): self
    {
        $this->cacheDisabled = true;

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function first(): ?Model
    {
        $result = $this->get();

        return $result instanceof Collection ? $result->first() : $result;
    }

    public function count(): int
    {
        $result = $this->get();

        return $result instanceof Collection ? $result->count() : 1;
    }

    /**
     * @return Model|Collection<int, Model>
     */
    public function get(string|int|null $id = null): Model|Collection
    {
        $payload = $this->fetchPayload($id);

        if ($id !== null) {
            return $this->model->newInstance($this->extract($payload));
        }

        return $this->hydrate($this->extract($payload));
    }

    /**
     * @param  array<int, string|int|null>  $ids
     * @return Collection<int, Model>
     */
    public function getMany(array $ids): Collection
    {
        $ids = array_values(array_filter($ids, fn ($id) => $id !== null && $id !== ''));
        $uris = array_map(fn ($id) => $this->uri($id), $ids);

        $items = array_map(
            fn (ResponseInterface $response) => $this->extract($this->decode($response)),
            $this->client()->getMany($uris),
        );

        return $this->hydrate($items);
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $config = $this->model->getPaginationConfig();

        if ($config === null || $config->total === null) {
            throw new RestException(sprintf(
                "Pagination total key is not configured for [%s]; set 'pagination.total' or use simplePaginate().",
                $this->model::class,
            ));
        }

        $page = $this->forPage($config, $perPage, $pageName, $page);
        $payload = $this->fetchPayload();
        $total = Arr::get($payload, $config->total);

        if (! is_int($total) && ! (is_string($total) && ctype_digit($total))) {
            throw new RestException(sprintf(
                'Pagination total key [%s] is missing or not an integer in the response for [%s].',
                $config->total,
                $this->model::class,
            ));
        }

        return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
            'items' => $this->hydrate($this->extract($payload)),
            'total' => (int) $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => $this->paginatorOptions($pageName),
        ]);
    }

    /**
     * @return Paginator<int, Model>
     */
    public function simplePaginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): Paginator
    {
        $config = $this->model->getPaginationConfig() ?? new PaginationConfig;

        $page = $this->forPage($config, $perPage, $pageName, $page);
        $payload = $this->fetchPayload();
        $items = $this->hydrate($this->extract($payload));

        return new RemotePaginator(
            $items,
            $perPage,
            $this->hasMorePages($config, $payload, $items->count(), $perPage),
            $page,
            $this->paginatorOptions($pageName),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(array $data = []): ?Model
    {
        $model = $this->model->fill($data);

        if (! $model->fireModelEvent('creating')) {
            return null;
        }

        $payload = $this->decode($this->client()->post($this->uri(), $this->envelope($model->getAttributes())));

        $created = $this->model->newInstance($this->extract($payload));
        $created->fireModelEvent('created');
        ($this->model)::flushCache();

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string|int|null $id = null, array $data = []): ?Model
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot update a model without an id or primary key value.');
        }

        $model = $this->model->fill($data);

        if (! $model->fireModelEvent('updating')) {
            return null;
        }

        $payload = $this->decode($this->client()->put($this->uri($id), $this->envelope($model->getAttributes())));

        $updated = $this->model->newInstance($this->extract($payload));
        $updated->fireModelEvent('updated');
        ($this->model)::flushCache();

        return $updated;
    }

    public function delete(string|int|null $id = null): bool
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot delete a model without an id or primary key value.');
        }

        if (! $this->model->fireModelEvent('deleting')) {
            return false;
        }

        $this->client()->delete($this->uri($id));

        $this->model->fireModelEvent('deleted');
        ($this->model)::flushCache();

        return true;
    }

    /**
     * @return array<mixed>
     */
    private function fetchPayload(string|int|null $id = null): array
    {
        return $this->decode($this->client()->get($this->uri($id), $this->grammar->compile($this->state)));
    }

    private function forPage(PaginationConfig $config, int $perPage, string $pageName, ?int $page): int
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException("Per page must be at least 1, [{$perPage}] given.");
        }

        if ($page !== null && $page < 1) {
            throw new InvalidArgumentException("Page must be at least 1, [{$page}] given.");
        }

        $page ??= max(1, (int) Paginator::resolveCurrentPage($pageName));

        $this->state->limit = $perPage;

        if ($config->style === 'offset') {
            $this->state->offset = ($page - 1) * $perPage;
            $this->state->page = null;
        } else {
            $this->state->page = $page;
            $this->state->offset = null;
        }

        return $page;
    }

    /**
     * @return array{path: string, pageName: string}
     */
    private function paginatorOptions(string $pageName): array
    {
        return ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function hasMorePages(PaginationConfig $config, array $payload, int $count, int $perPage): bool
    {
        if ($config->next !== null) {
            $next = Arr::get($payload, $config->next);

            return is_string($next) && $next !== '';
        }

        if ($config->hasMore !== null) {
            return Arr::get($payload, $config->hasMore) === true;
        }

        // ponytail: full-page inference — an exactly-full last page shows one extra empty page, and a server capping page size below perPage stops after page one; configure 'next' or 'has_more' to avoid both.
        return $count >= $perPage;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function envelope(array $attributes): array
    {
        $key = $this->model->getRequestDataKey();

        return $key === null ? $attributes : [$key => $attributes];
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        return $body === '' ? [] : Json::decode($body);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    private function extract(array $payload): array
    {
        $key = $this->model->getDataKey();

        if ($key === null) {
            return $payload;
        }

        return (array) Arr::get($payload, $key, []);
    }

    /**
     * @param  array<mixed>  $items
     * @return Collection<int, Model>
     */
    private function hydrate(array $items): Collection
    {
        return $this->model->newCollection(
            array_map(fn (mixed $item) => $this->model->newInstance(is_array($item) ? $item : []), array_values($items)),
        );
    }

    private function uri(string|int|null $id = null): string
    {
        $endpoint = $this->endpointOverride ?? $this->model->getEndpoint();

        return $id === null ? $endpoint : "{$endpoint}/{$id}";
    }

    private function client(): ClientInterface
    {
        $headers = array_merge($this->model->getHeaders(), $this->headers);

        $client = $this->model->getClient($headers === [] ? [] : ['headers' => $headers]);

        $ttl = $this->cacheDisabled ? null : ($this->cacheTtlOverride ?? $this->model->getCacheTtl());

        if ($ttl === null) {
            return $client;
        }

        $store = Model::getCacheStore();

        if ($store === null) {
            throw new RestException(
                'Caching requested but no cache store configured. Call Model::setCacheStore() or set rest.cache.'
            );
        }

        return new CachingClient(
            $client,
            $store,
            $this->model::class,
            $this->model->getClientName(),
            $ttl,
            $headers === [] ? null : md5(serialize($headers)),
        );
    }
}
