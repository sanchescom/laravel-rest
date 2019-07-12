<?php

namespace App\Rest\Clients;

use App\Rest\ClientInterface;
use InvalidArgumentException;
use Illuminate\Contracts\Container\Container;

class ClientFactory
{
    /**
     * The IoC container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * Create a new client factory instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Create a new client instance.
     *
     * @param array $config
     * @param null $name
     *
     * @return ClientInterface
     *
     */
    public function createClient(array $config = [], $name = null)
    {
        switch ($config['provider']) {
            case 'guzzle':
                return new GuzzleClient($config);
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['provider']}]");
    }
}
