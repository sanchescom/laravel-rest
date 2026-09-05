<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Support\Json;

final class Builder
{
    public function __construct(private readonly Model $model) {}

    /**
     * @return Model|Collection<int, Model>
     */
    public function get(string|int|null $id = null): Model|Collection
    {
        $payload = $this->decode($this->client()->get($this->uri($id), []));

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
    public function post(array $data = []): Model
    {
        $attributes = $this->model->fill($data)->getAttributes();

        $payload = $this->decode($this->client()->post($this->uri(), $attributes));

        return $this->model->newInstance($this->extract($payload));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string|int|null $id = null, array $data = []): Model
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot update a model without an id or primary key value.');
        }

        $attributes = $this->model->fill($data)->getAttributes();

        $payload = $this->decode($this->client()->put($this->uri($id), $attributes));

        return $this->model->newInstance($this->extract($payload));
    }

    public function delete(string|int|null $id = null): bool
    {
        $id ??= $this->model->getKey();

        if ($id === null) {
            throw new RestException('Cannot delete a model without an id or primary key value.');
        }

        $this->client()->delete($this->uri($id));

        return true;
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
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Model>
     */
    private function hydrate(array $items): Collection
    {
        return $this->model->newCollection(
            array_map(fn (array $item) => $this->model->newInstance($item), array_values($items)),
        );
    }

    private function uri(string|int|null $id = null): string
    {
        $endpoint = $this->model->getEndpoint();

        return $id === null ? $endpoint : "{$endpoint}/{$id}";
    }

    private function client(): ClientInterface
    {
        return $this->model->getClient();
    }
}
