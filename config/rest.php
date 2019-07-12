<?php

return [
    'default' => 'keto',

    'clients' => [

        'keto' => [

            'provider' => 'guzzle',

            'base_uri' => 'https://wl-iam.test.env/engines/acp/ory/regex/',

            'version' => '',

            'options' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],

            ],

        ],

    ],

];
