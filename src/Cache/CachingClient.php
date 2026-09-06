<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Cache;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;

final class CachingClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly CacheInterface $cache,
        private readonly string $modelClass,
        private readonly ?string $clientName,
        private readonly int $ttl,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        $key = $this->key($uri, $query);

        $hit = $this->cache->get($key);

        if (is_array($hit)) {
            return new Response($hit['status'], [], $hit['body']);
        }

        return $this->remember($key, $this->inner->get($uri, $query));
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
            $cached = $this->cache->get($this->key($uri, []));

            if (is_array($cached)) {
                $hits[$index] = new Response($cached['status'], [], $cached['body']);
            } else {
                $missing[$index] = $uri;
            }
        }

        $fetched = $missing === [] ? [] : $this->inner->getMany($missing);

        foreach ($fetched as $index => $response) {
            $fetched[$index] = $this->remember($this->key($missing[$index], []), $response);
        }

        $ordered = [];

        foreach (array_keys($uris) as $index) {
            $ordered[$index] = $hits[$index]
                ?? $fetched[$index]
                ?? throw new RestException("Client returned no response for [{$uris[$index]}].");
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
        $version = (int) $this->cache->get(CacheKeys::version($this->modelClass), 0);

        return CacheKeys::entry($this->clientName, $this->modelClass, $version, $uri, $query);
    }

    private function remember(string $key, ResponseInterface $response): ResponseInterface
    {
        $this->cache->set($key, [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
        ], $this->ttl);

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $response;
    }
}
