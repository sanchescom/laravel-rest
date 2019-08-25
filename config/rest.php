<?php

return [
    'default' => 'keto',

    'clients' => [
        'keto' => [
            'provider' => 'guzzle',
            'base_uri' => 'https://iam.test.env/engines/acp/ory/regex/',
            'options' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
        ],
    ],
];
