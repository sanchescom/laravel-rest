<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Jsonplaceholder;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;

final class Post extends Model
{
    protected ?string $endpoint = 'posts';

    protected ?string $dataKey = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'postId');
    }

    public function nestedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->nested();
    }
}

final class User extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'userId');
    }
}

final class Comment extends Model
{
    protected ?string $endpoint = 'comments';

    protected ?string $dataKey = null;
}

return [
    'name' => 'JSONPlaceholder',
    'docs' => 'https://jsonplaceholder.typicode.com/guide/',
    'base_uri' => 'https://jsonplaceholder.typicode.com/',
    'traits' => [
        'engine' => 'json-server',
        'response' => 'bare array / bare object',
        'pagination' => '_page + _limit; total only in X-Total-Count and Link headers',
        'filters' => 'field=value; repeated params for OR',
        'sort' => '_sort + _order',
        'keys' => 'integer id',
        'relations' => 'foreign-key filters and nested URLs',
        'writes' => 'faked, not persisted',
    ],
    'client' => [
        'query' => [
            'sort' => 'separate',
            'sort_names' => ['field' => '_sort', 'direction' => '_order'],
            'names' => ['page' => '_page', 'limit' => '_limit'],
        ],
    ],
    'scenarios' => [
        'list posts' => ['probe' => 'list', 'model' => Post::class, 'min' => 100, 'fields' => ['id', 'userId', 'title']],
        'find post' => ['probe' => 'find', 'model' => Post::class, 'id' => 1, 'fields' => ['title']],
        'get many posts' => ['probe' => 'get-many', 'model' => Post::class, 'ids' => [1, 2, 3]],
        'filter posts by user' => ['probe' => 'filter', 'model' => Post::class, 'field' => 'userId', 'value' => 1, 'min' => 10, 'features' => ['grammar.configurable']],
        'sort posts by id desc' => ['probe' => 'sort', 'model' => Post::class, 'field' => 'id', 'direction' => 'desc', 'features' => ['grammar.configurable']],
        'simple paginate posts' => ['probe' => 'simple-paginate', 'model' => Post::class, 'per_page' => 10, 'features' => ['paginate.style.page']],
        'lazy walk posts' => ['probe' => 'lazy', 'model' => Post::class, 'chunk' => 25, 'take' => 100],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'Total is only exposed in the X-Total-Count header; paginate() reads totals from the response body.',
        ],
        'membership filters' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in', 'eager.batch'],
            'reason' => 'json-server expects repeated params (id=1&id=2); whereIn renders a comma list or an indexed array (id[0]=1).',
        ],
        'post author' => ['probe' => 'belongs-to', 'model' => Post::class, 'id' => 1, 'relation' => 'author', 'foreign_key' => 'userId'],
        'user posts' => ['probe' => 'has-many', 'model' => User::class, 'id' => 1, 'relation' => 'posts', 'foreign_key' => 'userId'],
        'nested post comments' => ['probe' => 'has-many', 'model' => Post::class, 'id' => 1, 'relation' => 'nestedComments', 'nested' => true],
        'eager comments' => ['probe' => 'eager', 'model' => Post::class, 'query' => fn (Builder $query) => $query->limit(5), 'relation' => 'comments', 'mode' => 'concurrent'],
        'eager authors' => ['probe' => 'eager', 'model' => Post::class, 'query' => fn (Builder $query) => $query->limit(5), 'relation' => 'author', 'mode' => 'concurrent'],
        'create post' => ['probe' => 'write', 'op' => 'create', 'model' => Post::class, 'data' => ['title' => 'live', 'body' => 'check', 'userId' => 1]],
        'update post' => ['probe' => 'write', 'op' => 'update', 'model' => Post::class, 'id' => 1, 'data' => ['title' => 'live']],
        'patch post' => ['probe' => 'write', 'op' => 'patch', 'model' => Post::class, 'id' => 1, 'data' => ['title' => 'live'], 'client' => ['update_method' => 'patch']],
        'delete post' => ['probe' => 'write', 'op' => 'delete', 'model' => Post::class, 'id' => 1],
        'missing post' => ['probe' => 'not-found', 'model' => Post::class, 'id' => 999999],
        'response cache' => ['probe' => 'cache', 'kind' => 'response', 'model' => Post::class, 'id' => 1],
        'request memo' => ['probe' => 'cache', 'kind' => 'memo', 'model' => Post::class, 'id' => 1],
    ],
];
