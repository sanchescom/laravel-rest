<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Bluesky;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class AuthorFeed extends Model
{
    protected ?string $endpoint = 'app.bsky.feed.getAuthorFeed';

    protected ?string $dataKey = 'feed';
}

return [
    'name' => 'Bluesky AppView (XRPC)',
    'docs' => 'https://docs.bsky.app/docs/category/http-reference',
    'base_uri' => 'https://public.api.bsky.app/xrpc/',
    'traits' => [
        'response' => 'named-key:feed + cursor',
        'pagination' => 'cursor (limit + cursor)',
        'deviations' => 'XRPC method names in the URL',
        'errors' => '400 {"error","message"}',
    ],
    'scenarios' => [
        'author feed' => [
            'probe' => 'list',
            'model' => AuthorFeed::class,
            'query' => fn (Builder $query) => $query->withQuery(['actor' => 'bsky.app'])->limit(10),
            'min' => 1,
            'fields' => ['post.uri'],
        ],
        'cursor paging has no total or offset' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.simple', 'paginate.lazy'],
            'reason' => 'The next page is reachable only via an opaque cursor token returned in the body (cursor); there is no total count and no page/offset for paginate()/simplePaginate()/lazy() to send — paginate() requires pagination.total to be configured, which this endpoint has nothing to point at.',
            'attempt' => [
                'probe' => 'paginate',
                'model' => AuthorFeed::class,
                'query' => fn (Builder $query) => $query->withQuery(['actor' => 'bsky.app']),
                'per_page' => 10,
            ],
        ],
    ],
];
