<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Exceptions;

class RequestException extends RestException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly string $uri,
        public readonly int $status,
        public readonly array $body = [],
        public readonly ?string $errorsKey = null,
    ) {
        parent::__construct("REST request to [{$uri}] failed with status {$status}.");
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromStatus(string $uri, int $status, array $body = [], ?string $errorsKey = null): self
    {
        return match (true) {
            $status === 404 => new ModelNotFoundException($uri, $status, $body, $errorsKey),
            $status === 422 => new ValidationException($uri, $status, $body, $errorsKey),
            $status >= 500 => new ServerException($uri, $status, $body, $errorsKey),
            default => new self($uri, $status, $body, $errorsKey),
        };
    }
}
