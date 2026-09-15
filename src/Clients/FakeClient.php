<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\RestException;

final class FakeClient implements ClientInterface
{
    /** @var list<RecordedRequest> */
    public array $recorded = [];

    /**
     * @param  array<string, ResponseInterface>  $map
     */
    public function __construct(private readonly array $map) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        return $this->respond('GET', $uri, $query, []);
    }

    /**
     * @param  array<int|string, string>  $uris
     * @return array<int|string, ResponseInterface>
     */
    public function getMany(array $uris): array
    {
        $responses = [];

        foreach ($uris as $key => $uri) {
            $responses[$key] = $this->respond('GET', $uri, [], []);
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): ResponseInterface
    {
        return $this->respond('POST', $uri, [], $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): ResponseInterface
    {
        return $this->respond('PUT', $uri, [], $data);
    }

    public function delete(string $uri): ResponseInterface
    {
        return $this->respond('DELETE', $uri, [], []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    private function respond(string $method, string $uri, array $query, array $data): ResponseInterface
    {
        if (str_contains($uri, '?')) {
            [$uri, $queryString] = explode('?', $uri, 2);
            parse_str($queryString, $parsed);

            /** @var array<int|string, array<mixed>|string> $parsed */
            $query = array_merge($parsed, $query);
        }

        $this->recorded[] = new RecordedRequest($method, $uri, $query, $data);

        foreach ($this->map as $pattern => $response) {
            if (Str::is($pattern, $uri)) {
                $status = $response->getStatusCode();

                if ($status >= 400) {
                    $body = json_decode((string) $response->getBody(), true);
                    $response->getBody()->rewind();

                    throw RequestException::fromStatus($uri, $status, is_array($body) ? $body : []);
                }

                $response->getBody()->rewind();

                return $response;
            }
        }

        throw new RestException("No fake response defined for [{$method} {$uri}].");
    }
}
