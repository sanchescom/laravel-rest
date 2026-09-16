<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openalex;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Work extends Model
{
    protected ?string $endpoint = 'works';

    protected ?string $dataKey = 'results';
}

return [
    'name' => 'OpenAlex',
    'docs' => 'https://docs.openalex.org/',
    'base_uri' => 'https://api.openalex.org/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'results + meta',
        'pagination' => 'page + per-page; total in meta.count',
        'filters' => 'filter=field:value,field:value (colon pairs, | for OR)',
        'sort' => 'sort=field:asc|desc (suffix style)',
        'keys' => 'full URL ids (https://openalex.org/W...), not the path segment the API accepts',
        'rate_limit' => 'anonymous daily budget (observed X-RateLimit-Remaining 984, cost_usd in meta) — keep this catalog\'s scenario count and attempts minimal',
    ],
    'client' => [
        'query' => [
            'names' => ['limit' => 'per-page'],
            'sort' => 'suffix',
        ],
        'pagination' => ['style' => 'page', 'total' => 'meta.count'],
    ],
    'scenarios' => [
        'sort works by citations' => [
            'probe' => 'sort',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->limit(25)->withQuery(['select' => 'id,cited_by_count']),
            'field' => 'cited_by_count',
            'direction' => 'desc',
        ],
        'list works' => [
            'probe' => 'list',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->limit(25)->withQuery(['select' => 'id,display_name']),
            'min' => 25,
        ],
        'paginate works' => [
            'probe' => 'paginate',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->withQuery(['select' => 'id']),
            'per_page' => 25,
        ],
        'filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'query.where-in'],
            'reason' => 'Filters pack colon pairs into one param (filter=type:article,publication_year:2020, | for OR); the package has no grammar that renders this shape. No attempt here to conserve the anonymous daily request budget.',
        ],
        'find by id' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'read.get-many', 'relation.belongs-to'],
            'reason' => 'The id returned in results is a full URL (https://openalex.org/W2741809807) while the detail path takes the bare id (W2741809807); getKey() never equals the id used to request the model, and fk values are URLs too. No attempt here to conserve the anonymous daily request budget.',
        ],
        'cursor paging' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => 'Beyond 10k results, page-based paging stops working and only cursor=* tokens are accepted; lazy() has no cursor mode. No attempt here to conserve the anonymous daily request budget.',
        ],
    ],
];
