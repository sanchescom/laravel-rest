<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\ChicagoSocrata;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\Grammar;
use Sanchescom\Rest\Query\QueryState;

/**
 * SoQL needs $-prefixed params and a space-separated "field DESC" order
 * clause; none of the built-in configurable sort styles render that shape
 * (the "dash" default sends -field, which SoQL rejects as a type mismatch).
 */
final class SocrataGrammar implements Grammar
{
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            $query[$where['field']] = $where['value'];
        }

        if ($state->orders !== []) {
            $query['$order'] = implode(', ', array_map(
                fn (array $order) => $order['direction'] === 'desc' ? "{$order['field']} DESC" : $order['field'],
                $state->orders
            ));
        }

        if ($state->limit !== null) {
            $query['$limit'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['$offset'] = $state->offset;
        }

        return $query;
    }
}

final class Crime extends Model
{
    protected ?string $endpoint = 'ijzp-q8t2.json';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'id';

    protected ?string $grammar = SocrataGrammar::class;
}

final class CrimeRow extends Model
{
    protected ?string $endpoint = 'ijzp-q8t2';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'id';
}

return [
    'name' => 'City of Chicago (Socrata SODA)',
    'docs' => 'https://dev.socrata.com/docs/endpoints',
    'base_uri' => 'https://data.cityofchicago.org/resource/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'bare-array',
        'pagination' => 'offset ($limit + $offset); no totals (separate $select=count(*) needed)',
        'filters' => 'field=value exact; SoQL $where',
        'sort' => '$order=field [DESC] (space-separated, not the dash style default)',
        'keys' => 'string id',
        'rate_limit' => 'throttled without app token; slow (up to 8 s)',
    ],
    'client' => [
        'pagination' => ['style' => 'offset'],
    ],
    'scenarios' => [
        'list crimes' => [
            'probe' => 'list',
            'model' => Crime::class,
            'query' => fn (Builder $query) => $query->limit(50)->withQuery(['$select' => 'id,date,primary_type']),
            'min' => 50,
        ],
        'filter theft' => [
            'probe' => 'filter',
            'model' => Crime::class,
            'query' => fn (Builder $query) => $query->limit(50),
            'field' => 'primary_type',
            'value' => 'THEFT',
            'min' => 50,
        ],
        'sort by date' => [
            'probe' => 'sort',
            'model' => Crime::class,
            'query' => fn (Builder $query) => $query->limit(50)->withQuery(['$select' => 'id,date']),
            'field' => 'date',
            'direction' => 'asc',
        ],
        'simple paginate crimes' => [
            'probe' => 'simple-paginate',
            'model' => Crime::class,
            'query' => fn (Builder $query) => $query->withQuery(['$select' => 'id,date', '$order' => 'id']),
            'per_page' => 50,
        ],
        'lazy walk crimes' => [
            'probe' => 'lazy',
            'model' => Crime::class,
            'query' => fn (Builder $query) => $query->orderBy('id', 'asc')->withQuery(['$select' => 'id,date']),
            'chunk' => 50,
            'take' => 150,
        ],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'List responses carry no total; getting a count needs a separate $select=count(*) request that paginate() never issues.',
        ],
        'find crime' => [
            'probe' => 'find',
            'model' => CrimeRow::class,
            'id' => '13311263',
        ],
    ],
];
