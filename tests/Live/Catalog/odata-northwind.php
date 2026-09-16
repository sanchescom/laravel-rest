<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\OdataNorthwind;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\Grammar;
use Sanchescom\Rest\Query\QueryState;

/**
 * OData v4 needs $-prefixed params, "$filter=field op value" expressions and
 * a space-separated "$orderby=field desc"; none of the built-in configurable
 * sort styles render that shape (see docs/capabilities.md).
 */
final class ODataGrammar implements Grammar
{
    private const OPERATORS = ['>' => 'gt', '>=' => 'ge', '<' => 'lt', '<=' => 'le', '!=' => 'ne', '=' => 'eq'];

    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            $operator = self::OPERATORS[$where['operator']] ?? 'eq';
            $value = is_string($where['value']) ? "'{$where['value']}'" : $where['value'];
            $query['$filter'] = "{$where['field']} {$operator} {$value}";
        }

        if ($state->orders !== []) {
            $query['$orderby'] = implode(',', array_map(
                fn (array $order) => "{$order['field']} {$order['direction']}",
                $state->orders,
            ));
        }

        if ($state->limit !== null) {
            $query['$top'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['$skip'] = $state->offset;
        }

        return $query;
    }
}

final class ProductList extends Model
{
    protected ?string $endpoint = 'Products';

    protected ?string $dataKey = 'value';

    protected string $primaryKey = 'ProductID';
}

final class Product extends Model
{
    protected ?string $endpoint = 'Products';

    protected ?string $dataKey = 'value';

    protected string $primaryKey = 'ProductID';

    protected ?string $grammar = ODataGrammar::class;
}

return [
    'name' => 'OData Northwind v4',
    'docs' => 'https://www.odata.org/odata-services/',
    'base_uri' => 'https://services.odata.org/V4/Northwind/Northwind.svc/',
    'traits' => [
        'response' => 'value + @odata.context/@odata.count',
        'pagination' => '$top + $skip (+$count=true); total in @odata.count',
        'filters' => '$filter=UnitPrice gt 50',
        'sort' => '$orderby=Field desc (space-separated)',
        'keys' => 'Products(1) key predicates, not Products/1',
        'errors' => '400 JSON for a wrong-shaped path (curl-verified — not the XML the discovery notes assumed)',
    ],
    'client' => [
        'query' => ['names' => ['limit' => '$top', 'offset' => '$skip']],
        'pagination' => ['style' => 'offset', 'total' => '@odata.count'],
    ],
    'scenarios' => [
        'list products' => [
            'probe' => 'list',
            'model' => ProductList::class,
            'query' => fn (Builder $query) => $query->limit(20)->withQuery(['$count' => 'true']),
            'min' => 20,
        ],
        'paginate products' => [
            'probe' => 'paginate',
            'model' => ProductList::class,
            'query' => fn (Builder $query) => $query->withQuery(['$count' => 'true']),
            'per_page' => 20,
        ],
        'filter by category' => [
            'probe' => 'filter',
            'model' => Product::class,
            'field' => 'CategoryID',
            'value' => 1,
            'min' => 2,
        ],
        'sort by unit price' => [
            'probe' => 'sort',
            'model' => Product::class,
            'field' => 'UnitPrice',
            'direction' => 'desc',
        ],
        'key predicate detail' => [
            'probe' => 'unsupported',
            'features' => ['read.find'],
            'reason' => 'Detail addressing is a key predicate, Products(1), not a path segment; find() always renders Products/1, which curl-verified answers HTTP 400 JSON ("segment refers to a collection ... must be the last segment") even for an id that exists, so a real product can never be found this way. (The proper Products(999999) shape does answer a real 404 for a genuinely missing id — the error signalling itself is fine, only the URL shape is unreachable.)',
            'attempt' => ['probe' => 'find', 'model' => ProductList::class, 'id' => 1],
        ],
    ],
];
