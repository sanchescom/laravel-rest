<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Clients;

use InvalidArgumentException;
use Sanchescom\Rest\Contracts\ClientInterface;

class ClientFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function createClient(array $config): ClientInterface
    {
        $provider = $config['provider'] ?? null;

        return match ($provider) {
            'guzzle' => GuzzleClient::fromConfig($config),
            default => throw new InvalidArgumentException("Unsupported provider [{$provider}]."),
        };
    }
}
