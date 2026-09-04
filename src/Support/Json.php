<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Support;

use JsonException;
use Sanchescom\Rest\Exceptions\RestException;

final class Json
{
    /**
     * @return array<mixed>
     */
    public static function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RestException("Unable to decode response JSON: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($decoded)) {
            throw new RestException('Response JSON must decode to an array.');
        }

        return $decoded;
    }
}
