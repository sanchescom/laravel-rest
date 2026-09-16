<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Disney;

use Sanchescom\Rest\Model;

final class Character extends Model
{
    protected ?string $endpoint = 'character';

    protected ?string $dataKey = 'data';

    protected string $primaryKey = '_id';
}

return [
    'name' => 'Disney API',
    'docs' => 'https://disneyapi.dev/docs/',
    'base_uri' => 'https://api.disneyapi.dev/',
    'traits' => [
        'response' => 'info + data (array for list, object for detail)',
        'pagination' => 'page + pageSize; no total (info.count is the page count, info.totalPages)',
        'filters' => 'name= (fuzzy)',
        'keys' => 'int _id',
        'errors' => 'unknown id -> 200 {"data":[]}',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'pageSize']],
        'pagination' => ['style' => 'page', 'next' => 'info.nextPage'],
    ],
    'scenarios' => [
        'list characters' => [
            'probe' => 'list',
            'model' => Character::class,
            'min' => 50,
        ],
        'find character' => [
            'probe' => 'find',
            'model' => Character::class,
            'id' => 308,
            'fields' => ['name'],
        ],
        'get many characters' => [
            'probe' => 'get-many',
            'model' => Character::class,
            'ids' => [308, 45, 3942],
        ],
        'simple paginate characters' => [
            'probe' => 'simple-paginate',
            'model' => Character::class,
            'per_page' => 50,
        ],
        'lazy walk characters' => [
            'probe' => 'lazy',
            'model' => Character::class,
            'chunk' => 50,
            'take' => 150,
        ],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'info.count is the number of items on the current page, not the collection total (only info.totalPages is available).',
        ],
        'missing character' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'Unknown id returns HTTP 200 {"info":{"count":0},"data":[]} instead of a 404, so ModelNotFoundException never fires.',
            'attempt' => ['probe' => 'not-found', 'model' => Character::class, 'id' => 999999999],
        ],
        'name filter' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'name= is a fuzzy match, not exact equality: name=Mickey Mouse also returns "Lion (Mickey Mouse Works)", so filtered results are not all equal to the requested value.',
            'attempt' => ['probe' => 'filter', 'model' => Character::class, 'field' => 'name', 'value' => 'Mickey Mouse'],
        ],
    ],
];
