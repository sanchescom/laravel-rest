<?php

namespace App\Rest\Contracts;

interface ClientResolverInterface
{
    /**
     * Get a client instance.
     *
     * @param string|null $name
     * @param array $options
     *
     * @return \App\Rest\Contracts\ClientInterface
     */
    public function client($name = null, array $options = []);

    /**
     * Get the default client name.
     *
     * @return string
     */
    public function getDefaultClient();

    /**
     * Set the default client name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultClient($name);
}
