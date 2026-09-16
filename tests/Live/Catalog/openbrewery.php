<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openbrewery;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Brewery extends Model
{
    protected ?string $endpoint = 'breweries';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Open Brewery DB',
    'docs' => 'https://www.openbrewerydb.org/documentation',
    'base_uri' => 'https://api.openbrewerydb.org/v1/',
    'traits' => [
        'response' => 'bare-array',
        'pagination' => 'page + per_page; total only via a separate /breweries/meta request',
        'filters' => 'by_type/by_state/by_city/by_ids (comma)',
        'sort' => 'sort=field:asc|desc (suffix style)',
        'keys' => 'uuid',
        'errors' => '404 HTML',
    ],
    'client' => [
        'query' => [
            'names' => ['limit' => 'per_page'],
            'sort' => 'suffix',
        ],
    ],
    'scenarios' => [
        'list breweries' => [
            'probe' => 'list',
            'model' => Brewery::class,
            'query' => fn (Builder $query) => $query->where('by_type', 'micro')->limit(50),
            'min' => 50,
        ],
        'find brewery' => [
            'probe' => 'find',
            'model' => Brewery::class,
            'id' => '950180bd-29c9-46b3-ad0c-e6f09799ec7f',
            'fields' => ['name'],
        ],
        'get many breweries' => [
            'probe' => 'get-many',
            'model' => Brewery::class,
            'ids' => [
                '950180bd-29c9-46b3-ad0c-e6f09799ec7f',
                '85192a9c-58a4-48c3-bd9d-496d09d22aa3',
                '836cb05e-ee8f-4798-859e-fe0bedb0186a',
            ],
        ],
        'filter by type' => [
            'probe' => 'filter',
            'model' => Brewery::class,
            'field' => 'by_type',
            'attribute' => 'brewery_type',
            'value' => 'micro',
            'min' => 20,
        ],
        'where-in ids' => [
            'probe' => 'where-in',
            'model' => Brewery::class,
            'field' => 'by_ids',
            'attribute' => 'id',
            'values' => ['950180bd-29c9-46b3-ad0c-e6f09799ec7f', '85192a9c-58a4-48c3-bd9d-496d09d22aa3'],
        ],
        'sort by name' => [
            'probe' => 'sort',
            'model' => Brewery::class,
            'query' => fn (Builder $query) => $query->where('by_state', 'ohio')->limit(20),
            'field' => 'name',
            'direction' => 'asc',
        ],
        'simple paginate breweries' => [
            'probe' => 'simple-paginate',
            'model' => Brewery::class,
            'per_page' => 50,
        ],
        'lazy walk breweries' => [
            'probe' => 'lazy',
            'model' => Brewery::class,
            'chunk' => 50,
            'take' => 150,
        ],
        'missing brewery' => [
            'probe' => 'not-found',
            'model' => Brewery::class,
            'id' => 'doesnotexist',
        ],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'Totals are only available from a separate GET breweries/meta request; list responses carry no total.',
        ],
    ],
];
