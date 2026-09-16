<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Fiscaldata;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\JsonApiGrammar;

final class RateOfExchange extends Model
{
    protected ?string $endpoint = 'v1/accounting/od/rates_of_exchange';

    protected ?string $dataKey = 'data';

    protected ?string $grammar = JsonApiGrammar::class;

    // Rows are not individually addressable (no resource id, no detail
    // endpoint) and record_date repeats once per country, so there is no
    // unique key to compare across pages; blank it out so the paginate/lazy
    // probes' key-overlap and uniqueness checks are skipped instead of
    // failing on a false premise.
    protected string $primaryKey = '';
}

return [
    'name' => 'U.S. Treasury Fiscal Data',
    'docs' => 'https://fiscaldata.treasury.gov/api-documentation/',
    'base_uri' => 'https://api.fiscaldata.treasury.gov/services/api/fiscal_service/',
    'traits' => [
        'response' => 'jsonapi-style data + meta + links',
        'pagination' => 'page[number] + page[size]; total in meta.total-count; next in links.next',
        'filters' => 'filter=field:op:value (in:(a,b)), a single packed param',
        'sort' => 'sort=-field (JSON:API dash style)',
        'keys' => 'none; rows have no id and no detail endpoint',
        'errors' => '400 JSON for bad params; 404 for any /rates_of_exchange/{x} sub-path',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'meta.total-count', 'next' => 'links.next'],
    ],
    'scenarios' => [
        'list exchange rates' => [
            'probe' => 'list',
            'model' => RateOfExchange::class,
            'query' => fn (Builder $query) => $query->withQuery(['fields' => 'record_date,country,exchange_rate']),
            'min' => 50,
        ],
        'sort by record date' => [
            'probe' => 'sort',
            'model' => RateOfExchange::class,
            'query' => fn (Builder $query) => $query->withQuery(['fields' => 'record_date,country,exchange_rate']),
            'field' => 'record_date',
            'direction' => 'desc',
        ],
        'paginate rates' => [
            'probe' => 'paginate',
            'model' => RateOfExchange::class,
            'query' => fn (Builder $query) => $query->withQuery(['fields' => 'record_date,country,exchange_rate']),
            'per_page' => 50,
        ],
        'simple paginate rates' => [
            'probe' => 'simple-paginate',
            'model' => RateOfExchange::class,
            'query' => fn (Builder $query) => $query->withQuery(['fields' => 'record_date,country,exchange_rate']),
            'per_page' => 50,
        ],
        'invalid page size' => [
            'probe' => 'status',
            'model' => RateOfExchange::class,
            'query' => fn (Builder $query) => $query->withQuery(['page' => ['size' => 'abc']]),
            'status' => 400,
        ],
        'filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'query.where-in'],
            'reason' => 'Filters use a single packed param filter=field:op:value (field:in:(a,b)); a plain where() renders filter[country]=France, which the API silently ignores (curl-verified: still returns the unfiltered, unrelated default page instead of France-only rows).',
            'attempt' => ['probe' => 'filter', 'model' => RateOfExchange::class, 'field' => 'country', 'value' => 'France', 'min' => 1],
        ],
        'row detail' => [
            'probe' => 'unsupported',
            'features' => ['read.find'],
            'reason' => 'Rows have no id and no detail endpoint; any /rates_of_exchange/{x} sub-path 404s regardless of the value given.',
            'attempt' => ['probe' => 'find', 'model' => RateOfExchange::class, 'id' => '1'],
        ],
        'lazy walk' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => 'Rows have no unique key (record_date repeats once per country), so lazy()\'s duplicate-page guard and the probe\'s unique-key check never hold across pages.',
            'attempt' => [
                'probe' => 'lazy',
                'model' => RateOfExchange::class,
                'query' => fn (Builder $query) => $query->withQuery(['fields' => 'record_date,country,exchange_rate']),
                'chunk' => 50,
                'take' => 100,
            ],
        ],
    ],
];
