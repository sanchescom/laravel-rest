<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Github;

use Sanchescom\Rest\Model;

final class Issue extends Model
{
    protected ?string $endpoint = 'repos/laravel/framework/issues';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'number';
}

final class RepositorySearch extends Model
{
    protected ?string $endpoint = 'search/repositories';

    protected ?string $dataKey = null;
}

return [
    'name' => 'GitHub REST API (anonymous)',
    'docs' => 'https://docs.github.com/rest',
    'base_uri' => 'https://api.github.com/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'bare-array / bare-object',
        'pagination' => 'Link header only (per_page + page/after cursor)',
        'errors' => '422 {"message","errors":[...]}; 404 JSON',
        'rate_limit' => '60 req/h core, 10/min search, anonymous — this catalog is capped at 3 requests per live run',
    ],
    'scenarios' => [
        'find issue' => [
            'probe' => 'find',
            'model' => Issue::class,
            'id' => 1,
        ],
        'missing issue' => [
            'probe' => 'not-found',
            'model' => Issue::class,
            'id' => 99999999,
        ],
        'search without q' => [
            'probe' => 'status',
            'model' => RepositorySearch::class,
            'status' => 422,
        ],
        'link header paging' => [
            'probe' => 'unsupported',
            'features' => ['paginate.simple', 'paginate.total', 'paginate.lazy'],
            'reason' => 'next/last page info lives only in the Link header (with after= cursors), never in the body; none of the pagination probes have a body path to read. No attempt here: reproducing it would spend more of the 60 req/h anonymous budget than this catalog\'s 5-request ceiling allows.',
        ],
    ],
];
