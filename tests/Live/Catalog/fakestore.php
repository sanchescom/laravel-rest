<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Fakestore;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;

final class Product extends Model
{
    protected ?string $endpoint = 'products';

    protected ?string $dataKey = null;
}

final class User extends Model
{
    protected ?string $endpoint = 'users';

    protected ?string $dataKey = null;
}

final class Cart extends Model
{
    protected ?string $endpoint = 'carts';

    protected ?string $dataKey = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}

return [
    'name' => 'Fake Store API',
    'docs' => 'https://fakestoreapi.com/docs',
    'base_uri' => 'https://fakestoreapi.com/',
    'throttle_ms' => 500,
    'traits' => [
        'response' => 'bare-array / bare-object',
        'pagination' => 'none (limit only)',
        'sort' => 'sort=asc|desc (value only, by id); ignored on other fields',
        'keys' => 'int id',
        'relations' => 'carts.userId -> users/{id}',
        'writes' => 'faked',
        'errors' => 'unknown id -> 200 empty body',
    ],
    'scenarios' => [
        'list products' => ['probe' => 'list', 'model' => Product::class, 'min' => 20],
        'find product' => ['probe' => 'find', 'model' => Product::class, 'id' => 1],
        'get many products' => ['probe' => 'get-many', 'model' => Product::class, 'ids' => [1, 2, 3]],
        'cart owner' => ['probe' => 'belongs-to', 'model' => Cart::class, 'id' => 1, 'relation' => 'user', 'foreign_key' => 'userId'],
        'eager cart owners' => ['probe' => 'eager', 'model' => Cart::class, 'query' => fn (Builder $query) => $query->limit(5), 'relation' => 'user', 'mode' => 'concurrent', 'foreign_key' => 'userId'],
        'create product' => ['probe' => 'write', 'op' => 'create', 'model' => Product::class, 'data' => ['title' => 'live', 'price' => 1.5]],
        'update product' => ['probe' => 'write', 'op' => 'update', 'model' => Product::class, 'id' => 1, 'data' => ['title' => 'live']],
        'patch product' => ['probe' => 'write', 'op' => 'patch', 'model' => Product::class, 'id' => 1, 'data' => ['title' => 'live'], 'client' => ['update_method' => 'patch']],
        'delete product' => ['probe' => 'write', 'op' => 'delete', 'model' => Product::class, 'id' => 1],
        'sort' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'sort=asc|desc carries only a direction and always orders by id; none of the package\'s sort styles render a bare direction for an arbitrary field. Workaround: withQuery([\'sort\' => \'desc\']).',
            'attempt' => ['probe' => 'sort', 'model' => Product::class, 'field' => 'price', 'direction' => 'desc'],
        ],
        'missing product' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'Unknown id returns HTTP 200 with an empty body, so no ModelNotFoundException is thrown.',
            'attempt' => ['probe' => 'not-found', 'model' => Product::class, 'id' => 999999],
        ],
        'pagination' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.simple', 'paginate.lazy'],
            'reason' => 'Only a limit parameter exists; page/offset params are silently ignored (the default page style applies but the API ignores page) and there is no total in the response.',
            'attempt' => ['probe' => 'paginate', 'model' => Product::class, 'per_page' => 5],
        ],
    ],
];
