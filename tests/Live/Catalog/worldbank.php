<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Worldbank;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class CountryList extends Model
{
    protected ?string $endpoint = 'country';

    // Top-level response is a positional [meta, rows] tuple; index "1" is the rows array.
    protected ?string $dataKey = '1';
}

final class CountryDetail extends Model
{
    protected ?string $endpoint = 'country';

    // Same tuple shape, but a single-match detail response nests the one row at rows[0].
    protected ?string $dataKey = '1.0';
}

return [
    'name' => 'World Bank Indicators API',
    'docs' => 'https://datahelpdesk.worldbank.org/knowledgebase/articles/889392',
    'base_uri' => 'https://api.worldbank.org/v2/',
    'traits' => [
        'response' => 'positional [meta, rows] tuple; total in meta.total (index 0)',
        'pagination' => 'page + per_page',
        'sort' => 'none',
        'keys' => 'string id (ISO3)',
        'formats' => 'XML by default; every request needs format=json (every scenario here sets it explicitly)',
        'errors' => '200 with a [{"message":[...]}] error body instead of 404',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'per_page']],
        'pagination' => ['style' => 'page', 'total' => '0.total'],
    ],
    'scenarios' => [
        'list countries' => [
            'probe' => 'list',
            'model' => CountryList::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json'])->limit(50),
            'min' => 50,
        ],
        'find country' => [
            'probe' => 'find',
            'model' => CountryDetail::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']),
            'id' => 'BRA',
        ],
        'paginate countries' => [
            'probe' => 'paginate',
            'model' => CountryList::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']),
            'per_page' => 50,
        ],
        'lazy walk countries' => [
            'probe' => 'lazy',
            'model' => CountryList::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']),
            'chunk' => 100,
            'take' => 200,
        ],
        'missing country' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'An unknown id answers HTTP 200 with [{"message":[{"key":"Invalid value"}]}] instead of a 404, so ModelNotFoundException never fires.',
            'attempt' => ['probe' => 'not-found', 'model' => CountryDetail::class, 'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']), 'id' => 'XXXXX'],
        ],
    ],
];
