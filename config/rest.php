<?php

return [
    'default' => 'localhost',

    'clients' => [
        'localhost' => [
            'provider' => 'guzzle',
            'base_uri' => 'https://localhost/',
            'options' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
        ],
    ],
];
