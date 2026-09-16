<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Mygene;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Query extends Model
{
    protected ?string $endpoint = 'query';

    protected ?string $dataKey = 'hits';

    protected string $primaryKey = '_id';
}

final class Gene extends Model
{
    protected ?string $endpoint = 'gene';

    protected ?string $dataKey = null;

    protected string $primaryKey = '_id';
}

return [
    'name' => 'MyGene.info',
    'docs' => 'https://docs.mygene.info/en/latest/',
    'base_uri' => 'https://mygene.info/v3/',
    'traits' => [
        'response' => 'Elasticsearch hits + total (query) / bare-object (gene)',
        'pagination' => 'offset via from + size',
        'filters' => 'q= Lucene syntax only; bare field=value params are ignored',
        'keys' => '_id (entrez string)',
        'errors' => '404 {"code":404,"success":false}',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'size', 'offset' => 'from']],
        'pagination' => ['style' => 'offset', 'total' => 'total'],
    ],
    'scenarios' => [
        'query genes' => [
            'probe' => 'list',
            'model' => Query::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'cdk2'])->limit(10),
            'min' => 10,
        ],
        'paginate genes' => [
            'probe' => 'paginate',
            'model' => Query::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'cdk2']),
            'per_page' => 10,
        ],
        'lazy walk genes' => [
            'probe' => 'lazy',
            'model' => Query::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'cdk2']),
            'chunk' => 10,
            'take' => 30,
        ],
        'find gene' => [
            'probe' => 'find',
            'model' => Gene::class,
            'query' => fn (Builder $query) => $query->withQuery(['fields' => 'symbol,name']),
            'id' => '1017',
            'fields' => ['symbol'],
        ],
        'get many genes' => [
            'probe' => 'get-many',
            'model' => Gene::class,
            'ids' => ['1017', '1018', '1019'],
        ],
        'missing gene' => [
            'probe' => 'not-found',
            'model' => Gene::class,
            'id' => 'doesnotexistlive',
        ],
        'filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'Filtering only understands the Lucene q= syntax; a plain where() renders symbol=CDK2 as a bare top-level param, which mygene ignores entirely (curl-verified: still matches the whole index, ~93M hits, not just CDK2). Batch lookup by many ids is a POST-only endpoint, which the package cannot use for reads — not claimed as query.where-in here since no GET attempt demonstrates it.',
            'attempt' => ['probe' => 'filter', 'model' => Query::class, 'field' => 'symbol', 'value' => 'CDK2', 'min' => 1],
        ],
    ],
];
