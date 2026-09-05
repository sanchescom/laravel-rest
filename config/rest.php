<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default REST Client
    |--------------------------------------------------------------------------
    |
    | The name of the client from the list below that is used when no client
    | is requested explicitly (Model::$client is null).
    |
    */

    'default' => env('REST_CLIENT', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | REST Clients
    |--------------------------------------------------------------------------
    |
    | Each client defines a provider (driver) and its configuration. The
    | base_uri must end with a trailing slash so relative endpoints resolve
    | correctly. Options are passed to the underlying HTTP client.
    |
    */

    'clients' => [
        'localhost' => [
            'provider' => 'guzzle',
            'base_uri' => 'https://localhost/',
            'options' => [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ],
        ],
    ],
];
