<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Mailtm;

use Sanchescom\Rest\Model;

final class Domain extends Model
{
    protected ?string $endpoint = 'domains';

    protected ?string $dataKey = 'hydra:member';
}

return [
    'name' => 'mail.tm',
    'docs' => 'https://docs.mail.tm/',
    'base_uri' => 'https://api.mail.tm/',
    'traits' => [
        'response' => 'hydra:Collection (hydra:member, hydra:totalItems) — only for Accept: application/ld+json; plain application/json (the harness default) answers a bare array instead',
        'pagination' => 'page (hydra:view); next in hydra:view.hydra:next',
        'total_location' => 'body:hydra:totalItems',
        'keys' => 'id + @id IRI',
    ],
    'client' => [
        'options' => ['headers' => ['Accept' => 'application/ld+json']],
    ],
    'scenarios' => [
        'list domains' => [
            'probe' => 'list',
            'model' => Domain::class,
            'min' => 1,
            'fields' => ['domain'],
        ],
        'hydra paging has no total to test' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.simple'],
            'client' => ['pagination' => ['total' => 'hydra:totalItems']],
            'reason' => 'hydra:totalItems is a perfectly usable pagination.total (a colon-bearing key holds no dots, so Arr::get resolves it), but this instance publishes exactly one domain — curl-verified hydra:totalItems 1, and page=2 answers an empty hydra:member — so paginate()/simplePaginate() have no second page to walk and the total can never exceed one page. hydra:view here is a bare PartialCollectionView with no hydra:next member at all.',
            'attempt' => [
                'probe' => 'paginate',
                'model' => Domain::class,
                'per_page' => 1,
            ],
        ],
        'writes create a real mailbox' => [
            'probe' => 'unsupported',
            'features' => ['write.create'],
            'reason' => 'POST /accounts creates a real, permanent temporary mailbox on a public instance — not a sandbox — so it is not exercised here.',
        ],
    ],
];
