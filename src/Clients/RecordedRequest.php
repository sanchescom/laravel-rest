<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

final class RecordedRequest
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $data,
    ) {}

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
