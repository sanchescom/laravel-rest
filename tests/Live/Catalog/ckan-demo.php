<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\CkanDemo;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\Grammar;
use Sanchescom\Rest\Query\QueryState;

/**
 * CKAN's package_search is Solr underneath: a plain field=value param is
 * rejected outright (400 "Invalid search parameters"), filters need
 * fq=field:value, and sort needs a space-separated "field asc|desc" — none of
 * the built-in configurable styles render either shape.
 */
final class CkanSearchGrammar implements Grammar
{
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            $query['fq'] = "{$where['field']}:{$where['value']}";
        }

        if ($state->orders !== []) {
            $order = $state->orders[0];
            $query['sort'] = "{$order['field']} {$order['direction']}";
        }

        if ($state->limit !== null) {
            $query['rows'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['start'] = $state->offset;
        }

        return $query;
    }
}

final class PackageSearch extends Model
{
    protected ?string $endpoint = 'package_search';

    protected ?string $dataKey = 'result.results';

    protected string $primaryKey = 'name';
}

final class Dataset extends Model
{
    protected ?string $endpoint = 'package_search';

    protected ?string $dataKey = 'result.results';

    protected string $primaryKey = 'name';

    protected ?string $grammar = CkanSearchGrammar::class;
}

final class DatasetDetail extends Model
{
    protected ?string $endpoint = 'package_show';

    protected ?string $dataKey = 'result';

    protected string $primaryKey = 'name';
}

return [
    'name' => 'CKAN Action API (demo.ckan.org)',
    'docs' => 'https://docs.ckan.org/en/latest/api/',
    'base_uri' => 'https://demo.ckan.org/api/3/action/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'help/success/result envelope; nested:result.results',
        'pagination' => 'rows + start; total in result.count',
        'filters' => 'fq=field:value (Solr); a bare field=value param 400s',
        'sort' => 'sort="field asc|desc" (space-separated)',
        'keys' => 'name/uuid via ?id=',
        'deviations' => 'rpc action names in the URL',
        'errors' => '404/400 {"success":false,"error":{...}}',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'rows', 'offset' => 'start']],
        'pagination' => ['style' => 'offset', 'total' => 'result.count'],
    ],
    'scenarios' => [
        'search datasets' => [
            'probe' => 'list',
            'model' => PackageSearch::class,
            'query' => fn (Builder $query) => $query->limit(5),
            'min' => 5,
        ],
        'paginate datasets' => [
            'probe' => 'paginate',
            'model' => PackageSearch::class,
            'per_page' => 5,
        ],
        'filter by license' => [
            'probe' => 'filter',
            'model' => Dataset::class,
            'field' => 'license_id',
            'value' => 'cc-by',
            'min' => 2,
        ],
        'sort by modified date' => [
            'probe' => 'sort',
            'model' => Dataset::class,
            'field' => 'metadata_modified',
            'direction' => 'desc',
        ],
        'rpc detail' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'errors.not-found'],
            'reason' => 'Detail is package_show?id=<name> (an action name plus a query param), not a path segment; find() always appends the id as a path segment (package_show/<name>), which 404s with an HTML body even for a dataset that really exists.',
            'attempt' => ['probe' => 'find', 'model' => DatasetDetail::class, 'id' => 'my-sample-dataset-001'],
        ],
        'plain field filter is rejected' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'A bare field=value param is not silently ignored but actively rejected: package_search?license_id=uk-ogl answers HTTP 400 "Invalid search parameters: [\'license_id\']" — the default grammar has no other way to render a where() clause.',
            'attempt' => ['probe' => 'filter', 'model' => PackageSearch::class, 'field' => 'license_id', 'value' => 'uk-ogl'],
        ],
        'writes' => [
            'probe' => 'unsupported',
            'features' => ['write.create'],
            'reason' => 'package_create is a POST-only RPC action requiring an API token; the public demo instance is not an anonymous write sandbox.',
        ],
    ],
];
