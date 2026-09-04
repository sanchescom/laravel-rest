<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Exceptions;

class ValidationException extends RequestException
{
    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return (array) ($this->body['errors'] ?? []);
    }
}
