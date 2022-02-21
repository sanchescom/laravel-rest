<?php

namespace Sanchescom\Rest\Clients;

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
            default:
                return app($config['provider'], $config);
        }
    }
}
