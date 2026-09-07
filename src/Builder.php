<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Cache\CachingClient;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
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
        $payload = $this->decode($this->client()->get($this->uri($id), $this->grammar->compile($this->state)));

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
