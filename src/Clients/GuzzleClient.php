<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RequestException;

final class GuzzleClient implements ClientInterface
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{base_uri?: string, options?: array<string, mixed>}  $config
     */
    public static function fromConfig(array $config): self
    {
        $options = array_merge($config['options'] ?? [], [
            'base_uri' => $config['base_uri'] ?? null,
            'http_errors' => false,
        ]);

        return new self(new Client($options));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->get($uri, ['query' => $query]));
    }

    /**
     * @param  array<int|string, string>  $uris
     * @return array<int|string, ResponseInterface>
     */
    public function getMany(array $uris): array
    {
        $responses = [];

        $requests = function () use ($uris) {
            foreach ($uris as $key => $uri) {
                yield $key => fn () => $this->client->getAsync($uri);
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function (ResponseInterface $response, int|string $key) use (&$responses) {
                $responses[$key] = $response;
            },
        ]);

        $pool->promise()->wait();

        $ordered = [];

        foreach (array_keys($uris) as $key) {
            $ordered[$key] = $this->ensureSuccessful($uris[$key], $responses[$key]);
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->post($uri, ['json' => $data]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->put($uri, ['json' => $data]));
    }

    public function delete(string $uri): ResponseInterface
    {
        return $this->ensureSuccessful($uri, $this->client->delete($uri));
    }

    private function ensureSuccessful(string $uri, ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $body = json_decode((string) $response->getBody(), true);

            throw RequestException::fromStatus($uri, $status, is_array($body) ? $body : []);
        }

        return $response;
    }
}
