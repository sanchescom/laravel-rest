<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Cache;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;

final class MemoizingClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly string $modelClass,
        private readonly ?string $clientName,
        private readonly ?string $keyExtra = null,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        $key = $this->key($uri, $query);

        return $this->hit($key) ?? $this->remember($key, $this->inner->get($uri, $query));
    }

    /**
     * @param  array<int|string, string>  $uris
     * @return array<int|string, ResponseInterface>
     */
    public function getMany(array $uris): array
    {
        $hits = [];
        $missing = [];

        foreach ($uris as $index => $uri) {
            $hit = $this->hit($this->key($uri, []));

            if ($hit !== null) {
                $hits[$index] = $hit;
            } else {
                $missing[$index] = $uri;
            }
        }

        $fetched = $missing === [] ? [] : $this->inner->getMany($missing);

        $ordered = [];

        foreach (array_keys($uris) as $index) {
            if (isset($hits[$index])) {
                $ordered[$index] = $hits[$index];

                continue;
            }

            $response = $fetched[$index]
                ?? throw new RestException("Client returned no response for [{$uris[$index]}].");

            $ordered[$index] = $this->remember($this->key($uris[$index], []), $response);
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): ResponseInterface
    {
        return $this->inner->post($uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): ResponseInterface
    {
        return $this->inner->put($uri, $data);
    }

    public function delete(string $uri): ResponseInterface
    {
        return $this->inner->delete($uri);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function key(string $uri, array $query): string
    {
        return CacheKeys::memo($this->clientName, $this->modelClass, $uri, $query, $this->keyExtra);
    }

    private function hit(string $key): ?ResponseInterface
    {
        $entry = Memo::get($this->modelClass, $key);

        return $entry === null ? null : new Response($entry['status'], $entry['headers'], $entry['body']);
    }

    private function remember(string $key, ResponseInterface $response): ResponseInterface
    {
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $status = $response->getStatusCode();
        $headers = $response->getHeaders();
        $body = (string) $response->getBody();

        Memo::put($this->modelClass, $key, $status, $body, $headers);

        return new Response($status, $headers, $body);
    }
}
