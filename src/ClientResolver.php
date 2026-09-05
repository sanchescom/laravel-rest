<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use InvalidArgumentException;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

class ClientResolver implements ClientResolverInterface
{
    protected ?string $default = null;

    /**
     * @param  array<string, ClientInterface>  $clients
     */
    public function __construct(protected array $clients = []) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function client(?string $name = null, array $options = []): ClientInterface
    {
        $name ??= $this->default;

        if ($name === null || ! $this->hasClient($name)) {
            throw new InvalidArgumentException('Client ['.($name ?? 'default').'] not registered.');
        }

        return $this->clients[$name];
    }

    public function addClient(string $name, ClientInterface $client): void
    {
        $this->clients[$name] = $client;
    }

    public function hasClient(string $name): bool
    {
        return isset($this->clients[$name]);
    }

    public function setDefaultClient(string $name): void
    {
        $this->default = $name;
    }
}
