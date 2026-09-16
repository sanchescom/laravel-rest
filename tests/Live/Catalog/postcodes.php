<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Postcodes;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Outcode extends Model
{
    protected ?string $endpoint = 'outcodes';

    protected ?string $dataKey = 'result';

    protected string $primaryKey = 'outcode';
}

final class Postcode extends Model
{
    protected ?string $endpoint = 'postcodes';

    protected ?string $dataKey = 'result';

    protected string $primaryKey = 'postcode';
}

return [
    'name' => 'Postcodes.io',
    'docs' => 'https://postcodes.io/docs',
    'base_uri' => 'https://api.postcodes.io/',
    'traits' => [
        'response' => 'status + result (object or array)',
        'pagination' => 'none (limit on query)',
        'filters' => 'q=',
        'keys' => 'postcode / outcode strings',
        'errors' => '404 {"status":404,"error"}',
    ],
    'scenarios' => [
        'find outcode' => [
            'probe' => 'find',
            'model' => Outcode::class,
            'id' => 'SW1A',
        ],
        'get many outcodes' => [
            'probe' => 'get-many',
            'model' => Outcode::class,
            'ids' => ['SW1A', 'EC1A', 'M1'],
        ],
        'search postcodes' => [
            'probe' => 'list',
            'model' => Postcode::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'SW1A'])->limit(3),
            'min' => 3,
        ],
        'missing postcode' => [
            'probe' => 'not-found',
            'model' => Postcode::class,
            'id' => 'XX1',
        ],
        'bulk lookup' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in'],
            'reason' => 'Bulk lookup is a POST {"postcodes":[...]} read (returns a 200 with per-postcode results); the package treats any POST as a write, so there is no whereIn() mapping to it.',
        ],
        'postcode keys' => [
            'probe' => 'unsupported',
            'features' => ['read.find'],
            'reason' => 'find() itself works (see "find outcode"); this is a narrower key-normalisation gap: postcodes/SW1A2AA (no space) still resolves 200 but the API re-inserts the space in the response ("SW1A 2AA"), so getKey() never equals the squished id that was requested for this specific input format.',
            'attempt' => ['probe' => 'find', 'model' => Postcode::class, 'id' => 'SW1A2AA'],
        ],
    ],
];
