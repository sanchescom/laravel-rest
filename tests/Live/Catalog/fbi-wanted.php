<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\FbiWanted;

use Sanchescom\Rest\Model;

final class Wanted extends Model
{
    protected ?string $endpoint = 'list';

    protected ?string $dataKey = 'items';

    protected string $primaryKey = 'uid';
}

return [
    'name' => 'FBI Wanted',
    'docs' => 'https://www.fbi.gov/wanted/api',
    'base_uri' => 'https://api.fbi.gov/wanted/v1/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'items + total',
        'pagination' => 'page + pageSize; total in body:total',
        'filters' => 'field=value',
        'sort' => 'sort_on + sort_order',
        'keys' => 'uid',
        'errors' => '404 {"detail"}',
    ],
    'client' => [
        'query' => [
            'names' => ['limit' => 'pageSize'],
            'sort' => 'separate',
            'sort_names' => ['field' => 'sort_on', 'direction' => 'sort_order'],
        ],
        'pagination' => ['style' => 'page', 'total' => 'total'],
    ],
    'scenarios' => [
        'list wanted' => [
            'probe' => 'list',
            'model' => Wanted::class,
            'min' => 20,
        ],
        'filter poster classification' => [
            'probe' => 'filter',
            'model' => Wanted::class,
            'field' => 'poster_classification',
            'value' => 'missing',
            'min' => 5,
        ],
        'sort by modified' => [
            'probe' => 'sort',
            'model' => Wanted::class,
            'field' => 'modified',
            'direction' => 'desc',
        ],
        'paginate wanted' => [
            'probe' => 'paginate',
            'model' => Wanted::class,
            'per_page' => 20,
        ],
        'lazy walk wanted' => [
            'probe' => 'lazy',
            'model' => Wanted::class,
            'chunk' => 50,
            'take' => 150,
        ],
        'detail' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'errors.not-found'],
            'reason' => 'The documented detail path (@wanted-person/{uid}) returns 404 {"detail":"Not Found"} for a uid taken straight from the list, so there is no working per-id endpoint to back find().',
            'attempt' => ['probe' => 'find', 'model' => Wanted::class, 'id' => '109f69822bdb4bd9bd6ea15da5d4f19d'],
        ],
    ],
];
