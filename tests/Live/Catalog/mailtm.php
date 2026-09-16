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
            'reason' => 'hydra:totalItems is readable via a dotted dataKey, but only 1 domain exists on this instance so paginate()/simplePaginate() have no second page to walk; the next link lives at hydra:view."hydra:next" — a dot-notation path through a colon-bearing key that Arr::get cannot express as a single dotted string.',
        ],
        'writes create a real mailbox' => [
            'probe' => 'unsupported',
            'features' => ['write.create'],
            'reason' => 'POST /accounts creates a real, permanent temporary mailbox on a public instance — not a sandbox — so it is not exercised here.',
        ],
    ],
];
