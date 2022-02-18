<?php

namespace Sanchescom\Rest\Clients;

use InvalidArgumentException;

class ClientFactory
{
    /**
     * Create a new client instance.
     *
     * @param array $config
     *
     * @return \Sanchescom\Rest\Contracts\ClientInterface
     *
     */
    public function createClient(array $config = [])
    {
        switch ($config['provider']) {
            case 'guzzle':
                return new GuzzleClient($config);
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['provider']}]");
    }
}
