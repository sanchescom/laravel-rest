<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Europepmc;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Article extends Model
{
    protected ?string $endpoint = 'search';

    protected ?string $dataKey = 'resultList.result';

    protected string $primaryKey = 'id';
}

return [
    'name' => 'Europe PMC REST',
    'docs' => 'https://europepmc.org/RestfulWebService',
    'base_uri' => 'https://www.ebi.ac.uk/europepmc/webservices/rest/',
    'traits' => [
        'response' => 'nested:resultList.result + hitCount + nextCursorMark',
        'pagination' => 'cursor (pageSize + cursorMark); total in body:hitCount',
        'filters' => 'query= Lucene',
        'formats' => 'xml default, format=json',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'pageSize']],
        'pagination' => ['total' => 'hitCount'],
    ],
    'scenarios' => [
        'search articles' => [
            'probe' => 'list',
            'model' => Article::class,
            'query' => fn (Builder $query) => $query->withQuery(['query' => 'malaria', 'format' => 'json'])->limit(10),
            'min' => 10,
        ],
        'cursorMark paging' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.lazy'],
            'reason' => 'hitCount is a readable body total, but the next page is addressed only via the opaque nextCursorMark token from the previous response, not a page number or offset — curl-verified: page=1 and page=2 (or the page-name paginate() sends) answer the exact same two result ids with hitCount unchanged, so paginate() silently returns the same page twice instead of advancing.',
            'attempt' => [
                'probe' => 'paginate',
                'model' => Article::class,
                'query' => fn (Builder $query) => $query->withQuery(['query' => 'malaria', 'format' => 'json']),
                'per_page' => 2,
            ],
        ],
    ],
];
