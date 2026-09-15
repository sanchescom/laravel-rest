<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\SpaceflightNews;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Article extends Model
{
    protected ?string $endpoint = 'articles';

    protected ?string $dataKey = null;
}

final class ArticleList extends Model
{
    // Trailing slash: only ever fetched as a collection, and DRF's
    // APPEND_SLASH otherwise 301-redirects every request.
    protected ?string $endpoint = 'articles/';

    protected ?string $dataKey = 'results';
}

final class Report extends Model
{
    protected ?string $endpoint = 'reports/';

    protected ?string $dataKey = 'results';
}

return [
    'name' => 'Spaceflight News API',
    'docs' => 'https://api.spaceflightnewsapi.net/v4/docs/',
    'base_uri' => 'https://api.spaceflightnewsapi.net/v4/',
    'throttle_ms' => 500,
    'traits' => [
        'response' => 'results + count/next/previous (DRF)',
        'pagination' => 'limit + offset',
        'filters' => 'news_site comma list on the bare field name (not a django __in lookup); range filters are separate named params (published_at_gte)',
        'sort' => 'ordering=-published_at (restricted choices)',
        'keys' => 'int id',
        'errors' => '404 {"detail"}; 400 {"ordering":[...]}',
        'quirks' => 'DRF APPEND_SLASH: single-item GETs 301-redirect (list endpoints here use a trailing-slash endpoint to avoid it)',
    ],
    'client' => [
        'query' => ['preset' => 'django', 'names' => ['limit' => 'limit', 'sort' => 'ordering']],
        'pagination' => ['preset' => 'django', 'style' => 'offset'],
    ],
    'scenarios' => [
        'list articles' => ['probe' => 'list', 'model' => ArticleList::class, 'min' => 10],
        'find article' => ['probe' => 'find', 'model' => Article::class, 'id' => 39958, 'fields' => ['title']],
        'filter news site' => ['probe' => 'filter', 'model' => ArticleList::class, 'field' => 'news_site', 'value' => 'NASA', 'min' => 10],
        'where-in news sites' => ['probe' => 'where-in', 'model' => ArticleList::class, 'client' => ['query' => ['filters' => 'plain']], 'query' => fn (Builder $query) => $query->limit(10), 'field' => 'news_site', 'values' => ['SpaceNews', 'NASA']],
        'sort by published_at' => ['probe' => 'sort', 'model' => ArticleList::class, 'field' => 'published_at', 'direction' => 'desc'],
        'paginate articles' => ['probe' => 'paginate', 'model' => ArticleList::class, 'per_page' => 10],
        'simple paginate articles' => ['probe' => 'simple-paginate', 'model' => ArticleList::class, 'per_page' => 10],
        'lazy walk reports' => ['probe' => 'lazy', 'model' => Report::class, 'chunk' => 20, 'take' => 60],
        'rejected ordering' => ['probe' => 'status', 'model' => ArticleList::class, 'query' => fn (Builder $query) => $query->orderBy('id', 'desc'), 'status' => 400],
        'missing article' => ['probe' => 'not-found', 'model' => Article::class, 'id' => 99999999],
        'request memo' => ['probe' => 'cache', 'kind' => 'memo', 'model' => ArticleList::class],
        'range filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'Range filters are separate named params (published_at_gte) rather than field__op or field[op]. Workaround: withQuery([\'published_at_gte\' => \'2026-01-01\']).',
        ],
        'relations' => [
            'probe' => 'unsupported',
            'features' => ['relation.belongs-to', 'relation.has-many'],
            'reason' => 'launches/events are embedded arrays of {launch_id, provider}; there is no fk attribute to a sibling resource.',
        ],
        'get many articles' => [
            'probe' => 'unsupported',
            'features' => ['read.get-many'],
            'reason' => 'Every /articles/{id} detail request 301-redirects to add the trailing slash DRF requires; the client follows it, but the redirect hop is recorded as a second request per id, so getMany\'s exact-request-count assertion (one request per id) cannot be satisfied even though the data loads correctly.',
            'attempt' => ['probe' => 'get-many', 'model' => Article::class, 'ids' => [39958, 39957, 39956]],
        ],
    ],
];
