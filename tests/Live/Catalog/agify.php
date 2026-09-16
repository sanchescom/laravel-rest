<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Agify;

use Sanchescom\Rest\Model;

final class Prediction extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'name';
}

return [
    'name' => 'Agify.io',
    'docs' => 'https://agify.io/documentation',
    'base_uri' => 'https://api.agify.io/',
    'traits' => [
        'response' => 'bare-object (single) / bare-array (name[] batch)',
        'pagination' => 'none',
        'filters' => 'name= / name[]=',
        'rate_limit' => 'documented 100 names/day anonymous — one scenario only',
    ],
    'scenarios' => [
        'where-in names as array' => [
            'probe' => 'where-in',
            'model' => Prediction::class,
            'client' => ['query' => ['in' => 'array']],
            'field' => 'name',
            'values' => ['michael', 'jane'],
        ],
    ],
];
