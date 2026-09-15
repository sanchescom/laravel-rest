<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Contracts\PaginationConfigResolverInterface;

class ClientManager implements ClientResolverInterface, PaginationConfigResolverInterface
{
    /** @var array<string, ClientInterface> */
    protected array $clients = [];

    /** @var array<string, callable> */
    protected array $extensions = [];

    public function __construct(
        protected Repository $config,
        protected ClientFactory $factory,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function client(?string $name = null, array $options = []): ClientInterface
    {
        $name ??= $this->getDefaultClient();
        $key = $name.':'.md5(serialize($options));

        return $this->clients[$key] ??= $this->makeClient($name, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function makeClient(string $name, array $options = []): ClientInterface
    {
        $config = $this->configuration($name);
        $config['options'] = array_replace_recursive($config['options'] ?? [], $options);

        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $provider = $config['provider'] ?? '';

        if (isset($this->extensions[$provider])) {
            return call_user_func($this->extensions[$provider], $config, $name);
        }

        return $this->factory->createClient($config);
    }

    /**
     * @return array<string, mixed>
     */
    protected function configuration(string $name): array
    {
        $config = $this->config->get("rest.clients.{$name}");

        if ($config === null) {
            throw new InvalidArgumentException("Client [{$name}] not configured.");
        }

        return $config;
    }

    public function getDefaultClient(): string
    {
        return (string) $this->config->get('rest.default');
    }

    public function setDefaultClient(string $name): void
    {
        $this->config->set('rest.default', $name);
    }

    public function extend(string $name, callable $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }

    public function grammar(?string $name = null): ?string
    {
        $name ??= $this->getDefaultClient();

        $grammar = $this->config->get("rest.clients.{$name}.grammar");

        return is_string($grammar) ? $grammar : null;
    }

    public function queryConfig(?string $name = null): array|string|null
    {
        $name ??= $this->getDefaultClient();

        $query = $this->config->get("rest.clients.{$name}.query");

        return is_array($query) || is_string($query) ? $query : null;
    }

    public function paginationConfig(?string $name = null): array|string|null
    {
        $name ??= $this->getDefaultClient();

        $pagination = $this->config->get("rest.clients.{$name}.pagination");

        return is_array($pagination) || is_string($pagination) ? $pagination : null;
    }
}
