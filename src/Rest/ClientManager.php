<?php

namespace App\Rest;

use App\Rest\Clients\ClientFactory;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class ClientManager implements ClientResolverInterface
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The database connection factory instance.
     *
     * @var \App\Rest\Clients\ClientFactory
     */
    protected $factory;

    /**
     * The active connection instances.
     *
     * @var array
     */
    protected $clients = [];

    /**
     * The custom connection resolvers.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Create a new database manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \App\Rest\Clients\ClientFactory  $factory
     * @return void
     */
    public function __construct($app, ClientFactory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;
    }

    /**
     * Get a database connection instance.
     *
     * @param string|null $name
     * @param array $options
     *
     * @return ClientInterface
     */
    public function client($name = null, array $options = [])
    {
        if (! isset($this->clients[$name])) {
            $this->clients[$name] = $this->makeClient($name, $options);
        }

        return $this->clients[$name];
    }

    /**
     * Make the database connection instance.
     *
     * @param string $name
     * @param array $options
     * @return ClientInterface
     */
    protected function makeClient($name, array $options = [])
    {
        $config = array_merge_recursive($this->configuration($name), ['options' => $options]);

        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        if (isset($this->extensions[$provider = $config['provider']])) {
            return call_user_func($this->extensions[$provider], $config, $name);
        }

        return $this->factory->createClient($config, $name);
    }

    /**
     * Get the configuration for a connection.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function configuration($name)
    {
        $name = $name ?: $this->getDefaultClient();

        $clients = $this->app['config']['rest.clients'];

        if (is_null($config = Arr::get($clients, $name))) {
            throw new InvalidArgumentException("Client [{$name}] not configured.");
        }

        return $config;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultClient()
    {
        return $this->app['config']['rest.default'];
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultClient($name)
    {
        $this->app['config']['rest.default'] = $name;
    }

    /**
     * Get all of the support drivers.
     *
     * @return array
     */
    public function supportedDrivers()
    {
        return ['guzzle'];
    }

    /**
     * Register an extension connection resolver.
     *
     * @param  string    $name
     * @param  callable  $resolver
     * @return void
     */
    public function extend($name, callable $resolver)
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * Return all of the created connections.
     *
     * @return array
     */
    public function getClients()
    {
        return $this->clients;
    }
}
