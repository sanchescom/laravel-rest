<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openlibrary;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class WorkSearchResult extends Model
{
    protected ?string $endpoint = 'search.json';

    protected ?string $dataKey = 'docs';

    protected string $primaryKey = 'key';
}

final class WorkEntity extends Model
{
    protected ?string $endpoint = 'works';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Open Library Search',
    'docs' => 'https://openlibrary.org/dev/docs/api/search',
    'base_uri' => 'https://openlibrary.org/',
    'traits' => [
        'response' => 'named-key:docs, Solr-style numFound',
        'pagination' => 'limit + offset; total in numFound',
        'filters' => 'q plus title=/author= search params',
        'sort' => 'sort=new|old|rating (fixed keywords, not field+direction)',
        'keys' => 'path keys, e.g. "/works/OL27479W" under a key field; ids never appear as a bare "id" attribute',
    ],
    'client' => [
        'pagination' => ['style' => 'offset', 'total' => 'numFound'],
    ],
    'scenarios' => [
        'search works' => [
            'probe' => 'list',
            'model' => WorkSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'tolkien', 'fields' => 'key,title'])->limit(20),
            'min' => 20,
        ],
        'paginate search' => [
            'probe' => 'paginate',
            'model' => WorkSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'tolkien', 'fields' => 'key,title']),
            'per_page' => 20,
        ],
        'lazy walk search' => [
            'probe' => 'lazy',
            'model' => WorkSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'tolkien', 'fields' => 'key,title']),
            'chunk' => 20,
            'take' => 60,
        ],
        'entity detail' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'relation.belongs-to'],
            'reason' => 'With the Accept: application/json header the harness always sends, GET works/OL27479W answers 200 JSON directly (curl-verified, no redirect); the attempt fails because the entity\'s id is the path string "/works/OL27479W" under a key field, which never appears as a bare id attribute, so read.find\'s key comparison (getKey() reads "id") cannot hold.',
            'attempt' => ['probe' => 'find', 'model' => WorkEntity::class, 'id' => 'OL27479W'],
        ],
        'sort' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'sort takes fixed keywords (new, old, rating), not field + direction; orderBy() renders sort=title (ignored, curl-verified) and sort=-title (curl-verified: 500 Internal Server Error).',
            'attempt' => [
                'probe' => 'sort',
                'model' => WorkSearchResult::class,
                'query' => fn (Builder $query) => $query->withQuery(['q' => 'tolkien', 'fields' => 'key,title']),
                'field' => 'title',
                'direction' => 'asc',
            ],
        ],
    ],
];
