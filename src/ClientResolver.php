<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use InvalidArgumentException;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

class ClientResolver implements ClientResolverInterface
{
    protected ?string $default = null;

    /** @var array<string, string> */
    protected array $grammars = [];

    /** @var array<string, array<string, mixed>|string> */
    protected array $queryConfigs = [];

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

    public function setGrammar(string $client, string $grammarClass): void
    {
        $this->grammars[$client] = $grammarClass;
    }

    public function grammar(?string $name = null): ?string
    {
        return $this->grammars[$name ?? $this->default ?? ''] ?? null;
    }

    /**
     * @param  array<string, mixed>|string  $config
     */
    public function setQueryConfig(string $client, array|string $config): void
    {
        $this->queryConfigs[$client] = $config;
    }

    public function queryConfig(?string $name = null): array|string|null
    {
        return $this->queryConfigs[$name ?? $this->default ?? ''] ?? null;
    }
}
