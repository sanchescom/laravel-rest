<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Exceptions;

use Illuminate\Support\Arr;

class ValidationException extends RequestException
{
    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return (array) Arr::get($this->body, $this->errorsKey ?? 'errors', []);
    }
}
