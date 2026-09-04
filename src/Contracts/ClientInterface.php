<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Contracts;

use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): ResponseInterface;

    /**
     * @param  array<int|string, string>  $uris
     * @return array<int|string, ResponseInterface> same keys/order as $uris
     */
    public function getMany(array $uris): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): ResponseInterface;

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): ResponseInterface;

    public function delete(string $uri): ResponseInterface;
}
