<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Nobel;

use Sanchescom\Rest\Model;

final class Laureate extends Model
{
    protected ?string $endpoint = 'laureates';

    protected ?string $dataKey = 'laureates';
}

final class LaureateDetail extends Model
{
    protected ?string $endpoint = 'laureate';

    protected ?string $dataKey = '0';
}

return [
    'name' => 'Nobel Prize API 2.1',
    'docs' => 'https://www.nobelprize.org/about/developer-zone-2/',
    'base_uri' => 'https://api.nobelprize.org/2.1/',
    'traits' => [
        'response' => 'named-key:laureates + meta + links; detail is a one-element array',
        'pagination' => 'offset + limit; total in body:meta.count; next in body:links.next',
        'filters' => 'field=value (gender, nobelPrizeCategory comma)',
        'sort' => 'sort=asc|desc (value only, always by name)',
        'keys' => 'string id',
        'errors' => 'unknown id -> 200 [{"meta":..}]',
    ],
    'client' => [
        'pagination' => ['style' => 'offset', 'total' => 'meta.count', 'next' => 'links.next'],
    ],
    'scenarios' => [
        'list laureates' => [
            'probe' => 'list',
            'model' => Laureate::class,
            'min' => 25,
        ],
        'filter gender' => [
            'probe' => 'filter',
            'model' => Laureate::class,
            'field' => 'gender',
            'value' => 'female',
            'min' => 25,
        ],
        'find laureate' => [
            'probe' => 'find',
            'model' => LaureateDetail::class,
            'id' => '1',
        ],
        'get many laureates' => [
            'probe' => 'get-many',
            'model' => LaureateDetail::class,
            'ids' => ['1', '2', '3'],
        ],
        'paginate laureates' => [
            'probe' => 'paginate',
            'model' => Laureate::class,
            'per_page' => 25,
        ],
        'simple paginate laureates' => [
            'probe' => 'simple-paginate',
            'model' => Laureate::class,
            'per_page' => 25,
        ],
        'lazy walk laureates' => [
            'probe' => 'lazy',
            'model' => Laureate::class,
            'chunk' => 25,
            'take' => 75,
        ],
        'sort' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'sort=asc|desc carries only a direction, always ordering by name; there is no field parameter, so orderBy() on another field cannot change the order (curl-verified: sort=asc and sort=desc both order by knownName regardless of requested field).',
        ],
        'missing laureate' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'Unknown id returns HTTP 200 with a meta-only single-element array (no laureate, no id) instead of a 404, so ModelNotFoundException never fires.',
            'attempt' => ['probe' => 'not-found', 'model' => LaureateDetail::class, 'id' => '9999999'],
        ],
    ],
];
