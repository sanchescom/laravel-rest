<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Github;

use Sanchescom\Rest\Builder;
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

    protected ?string $dataKey = 'items';
}

return [
    'name' => 'GitHub REST API (anonymous)',
    'docs' => 'https://docs.github.com/rest',
    'base_uri' => 'https://api.github.com/',
    'throttle_ms' => 1000,
    'client' => [
        'query' => ['names' => ['limit' => 'per_page']],
    ],
    'traits' => [
        'response' => 'bare-array / bare-object',
        'pagination' => 'Link header only (per_page + page/after cursor)',
        'errors' => '422 {"message","errors":[...]}; 404 JSON',
        'rate_limit' => '60 req/h core, 10/min search, anonymous — this catalog is capped at 5 requests per live run',
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
        'simple paginate repositories' => [
            'probe' => 'simple-paginate',
            'model' => RepositorySearch::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'language:php']),
            'per_page' => 2,
        ],
        'link header total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'No pagination.total is configured here, so paginate() has no body path to a total; the true count lives only in the Link header\'s rel="last" page number. No attempt: verifying it would spend more of the anonymous rate-limit budget than this catalog\'s 5-request ceiling allows. paginate.simple and paginate.lazy are NOT actually blocked by this — both rely on full-page inference (count>=perPage), which the "simple paginate repositories" scenario above proves works now that names.limit maps to per_page.',
        ],
    ],
];
