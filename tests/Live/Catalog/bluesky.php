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
            'client' => ['pagination' => ['total' => 'total']],
            'reason' => 'The next page is reachable only via an opaque cursor token returned in the body; the whole envelope is curl-verified {feed, cursor} — no count, no page number, no offset. The attempt configures pagination.total at a plain "total" key to show the response has nothing to hold it: paginate() fetches the page and then finds no total in the body. simplePaginate()/lazy() are stuck on the same wall from the other side — page= is accepted and ignored, so page=1 and page=2 curl-verified answer the identical ten post uris.',
            'attempt' => [
                'probe' => 'paginate',
                'model' => AuthorFeed::class,
                'query' => fn (Builder $query) => $query->withQuery(['actor' => 'bsky.app']),
                'per_page' => 10,
            ],
        ],
    ],
];
