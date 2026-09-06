<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Auth\AuthInterface;
use Sanchescom\Rest\Auth\BasicAuth;
use Sanchescom\Rest\Auth\BearerAuth;
use Sanchescom\Rest\Auth\HeaderAuth;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RequestException;

final class GuzzleClient implements ClientInterface
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{base_uri?: string, options?: array<string, mixed>, auth?: array<string, mixed>, retry?: array<string, mixed>}  $config
     */
    public static function fromConfig(array $config): self
    {
        $options = $config['options'] ?? [];

        $stack = $options['handler'] ?? HandlerStack::create();

        if (isset($config['auth'])) {
            $auth = self::makeAuth($config['auth']);
            $stack->push(Middleware::mapRequest(
                fn ($request) => $auth->authenticate($request),
            ), 'rest_auth');
        }

        $options = array_merge($options, [
            'handler' => $stack,
            'base_uri' => $config['base_uri'] ?? null,
            'http_errors' => false,
        ]);

        return new self(new Client($options));
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private static function makeAuth(array $auth): AuthInterface
    {
        $driver = $auth['driver'] ?? null;

        return match ($driver) {
            'bearer' => new BearerAuth((string) $auth['token']),
            'basic' => new BasicAuth((string) $auth['username'], (string) $auth['password']),
            'header' => new HeaderAuth((array) $auth['headers']),
            default => is_string($driver) && is_a($driver, AuthInterface::class, true)
                ? new $driver($auth)
                : throw new InvalidArgumentException("Unsupported auth driver [{$driver}]."),
        };
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
