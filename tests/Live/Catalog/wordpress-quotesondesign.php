<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\WordpressQuotesondesign;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;

final class Post extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author');
    }
}

final class User extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Quotes on Design (WordPress REST API)',
    'docs' => 'https://developer.wordpress.org/rest-api/reference/',
    'base_uri' => 'https://quotesondesign.com/wp-json/wp/v2/',
    'traits' => [
        'response' => 'bare-array',
        'pagination' => 'page + per_page',
        'total_location' => 'header:X-WP-Total (+X-WP-TotalPages); next only in header:Link',
        'filters' => 'author=, categories=, include=',
        'sort' => 'orderby+order',
        'keys' => 'int id',
        'errors' => '404/400 {"code","message","data":{"status"}}',
    ],
    'client' => [
        'query' => [
            'names' => ['limit' => 'per_page'],
            'sort' => 'separate',
            'sort_names' => ['field' => 'orderby', 'direction' => 'order'],
        ],
    ],
    'scenarios' => [
        'list posts' => [
            'probe' => 'list',
            'model' => Post::class,
            'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id,author,title'])->limit(10),
            'min' => 10,
        ],
        'find post' => [
            'probe' => 'find',
            'model' => Post::class,
            'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id,author']),
            'id' => 2568,
        ],
        'filter by author' => [
            'probe' => 'filter',
            'model' => Post::class,
            'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id,author']),
            'field' => 'author',
            'value' => 2,
            'min' => 10,
        ],
        'sort by id asc' => [
            'probe' => 'sort',
            'model' => Post::class,
            'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id'])->limit(5),
            'field' => 'id',
            'direction' => 'asc',
        ],
        'simple paginate posts' => [
            'probe' => 'simple-paginate',
            'model' => Post::class,
            'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id']),
            'per_page' => 10,
        ],
        'lazy walk posts' => [
            'probe' => 'lazy',
            'model' => Post::class,
            'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id']),
            'chunk' => 100,
            'take' => 300,
        ],
        'author relation' => [
            'probe' => 'belongs-to',
            'model' => Post::class,
            'id' => 2568,
            'relation' => 'authorUser',
            'foreign_key' => 'author',
        ],
        'missing post' => [
            'probe' => 'not-found',
            'model' => Post::class,
            'id' => 99999999,
        ],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'Totals only exist in the X-WP-Total / X-WP-TotalPages response headers, not the body; paginate() has no body path to read a total from.',
            'attempt' => ['probe' => 'paginate', 'model' => Post::class, 'query' => fn (Builder $query) => $query->withQuery(['_fields' => 'id']), 'per_page' => 10],
        ],
        'past last page' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => 'Requesting a page beyond the last returns HTTP 400 rest_post_invalid_page_number instead of an empty array (curl-verified). lazy() has no total/has_more signal configured for this API, so it falls back to full-page inference (count>=perPage); whenever the walk\'s true last page happens to be exactly full, lazy() requests one more page and that request throws instead of stopping cleanly. Not attempted live: the post count drifts over time, so a fixed chunk size cannot reliably reproduce the exact-boundary case on every run.',
        ],
    ],
];
