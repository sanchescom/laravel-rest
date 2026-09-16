<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openfda;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class AdverseEvent extends Model
{
    protected ?string $endpoint = 'drug/event.json';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'safetyreportid';
}

return [
    'name' => 'openFDA',
    'docs' => 'https://open.fda.gov/apis/',
    'base_uri' => 'https://api.fda.gov/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'meta + results',
        'pagination' => 'limit + skip; total in meta.results.total; next only in the Link header',
        'filters' => 'search=field:value (Lucene syntax); a bare field=value param is rejected',
        'sort' => 'sort=field:asc|desc (suffix style)',
        'keys' => 'safetyreportid',
        'errors' => '400 JSON for a bad param; 404 JSON for a route/search with no matches',
        'rate_limit' => 'documented 240/min, 1000/day without a key',
    ],
    'client' => [
        'query' => [
            'names' => ['offset' => 'skip'],
            'sort' => 'suffix',
        ],
        'pagination' => ['style' => 'offset', 'total' => 'meta.results.total'],
    ],
    'scenarios' => [
        'list adverse events' => [
            'probe' => 'list',
            'model' => AdverseEvent::class,
            'query' => fn (Builder $query) => $query->limit(5),
            'min' => 5,
        ],
        'sort by receive date' => [
            'probe' => 'sort',
            'model' => AdverseEvent::class,
            'query' => fn (Builder $query) => $query->limit(5),
            'field' => 'receivedate',
            'direction' => 'desc',
        ],
        'paginate adverse events' => [
            'probe' => 'paginate',
            'model' => AdverseEvent::class,
            'per_page' => 5,
        ],
        'search filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'Filters are a single Lucene search param (search=patient.drug.medicinalproduct:"ASPIRIN"); a plain where() renders the field name as its own top-level param, which the API rejects outright.',
            'attempt' => ['probe' => 'filter', 'model' => AdverseEvent::class, 'field' => 'patient.drug.medicinalproduct', 'value' => 'ASPIRIN'],
        ],
        'detail and not-found' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'errors.not-found'],
            'reason' => 'There is no path-based detail endpoint (only the list endpoint exists); an empty search answers 404 {"error":{"code":"NOT_FOUND"}} instead of a per-id 404, so read.find has nothing to call.',
            'attempt' => ['probe' => 'find', 'model' => AdverseEvent::class, 'id' => 'doesnotexist123'],
        ],
        'deep paging' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => 'skip is capped at 25000 and the next page is only exposed via the Link response header, which lazy() does not read. No attempt here: reproducing the cap would need thousands of requests against a daily quota.',
        ],
    ],
];
