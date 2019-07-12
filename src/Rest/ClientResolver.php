<?php

namespace App\Rest;

class ClientResolver implements ClientResolverInterface
{
    /**
     * All of the registered clients.
     *
     * @var array
     */
    protected $clients = [];

    /**
     * The default client name.
     *
     * @var string
     */
    protected $default;

    /**
     * Create a new client resolver instance.
     *
     * @param  array  $clients
     * @return void
     */
    public function __construct(array $clients = [])
    {
        foreach ($clients as $name => $client) {
            $this->addClient($name, $client);
        }
    }

    /**
     * Get a database client instance.
     *
     * @param string|null $name
     * @param array $options
     *
     * @return \App\Rest\ClientInterface
     */
    public function client($name = null, array $options = [])
    {
        if (is_null($name)) {
            $name = $this->getDefaultClient();
        }

        return $this->clients[$name];
    }

    /**
     * Add a client to the resolver.
     *
     * @param  string  $name
     * @param  \App\Rest\ClientInterface  $client
     * @return void
     */
    public function addClient($name, ClientInterface $client)
    {
        $this->clients[$name] = $client;
    }

    /**
     * Check if a client has been registered.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasClient($name)
    {
        return isset($this->clients[$name]);
    }

    /**
     * Get the default client name.
     *
     * @return string
     */
    public function getDefaultClient()
    {
        return $this->default;
    }

    /**
     * Set the default client name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultClient($name)
    {
        $this->default = $name;
    }
}
