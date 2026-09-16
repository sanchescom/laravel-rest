<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Ukpolice;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

final class Force extends Model
{
    protected ?string $endpoint = 'forces';

    protected ?string $dataKey = null;

    public function people(): HasMany
    {
        return $this->hasMany(Person::class)->nested();
    }
}

final class Person extends Model
{
    protected ?string $endpoint = 'people';

    protected ?string $dataKey = null;
}

return [
    'name' => 'data.police.uk',
    'docs' => 'https://data.police.uk/docs/',
    'base_uri' => 'https://data.police.uk/api/',
    'traits' => [
        'response' => 'bare-array / bare-object',
        'pagination' => 'none (reference endpoints return everything)',
        'keys' => 'slug id',
        'relations' => 'forces/{id}/people nested',
        'errors' => '404 text/plain',
        'rate_limit' => 'documented 15 req/s',
    ],
    'scenarios' => [
        'list forces' => [
            'probe' => 'list',
            'model' => Force::class,
            'min' => 40,
            'fields' => ['id', 'name'],
        ],
        'find force' => [
            'probe' => 'find',
            'model' => Force::class,
            'id' => 'leicestershire',
            'fields' => ['telephone'],
        ],
        'get many forces' => [
            'probe' => 'get-many',
            'model' => Force::class,
            'ids' => ['leicestershire', 'metropolitan', 'kent'],
        ],
        'force people' => [
            'probe' => 'has-many',
            'model' => Force::class,
            'id' => 'leicestershire',
            'relation' => 'people',
            'nested' => true,
            'path_suffix' => 'forces/leicestershire/people',
        ],
        'missing force' => [
            'probe' => 'not-found',
            'model' => Force::class,
            'id' => 'doesnotexistlive',
        ],
        'request memo' => [
            'probe' => 'cache',
            'model' => Force::class,
            'kind' => 'memo',
            'id' => 'leicestershire',
        ],
        'paging/filter/sort' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy', 'query.filter', 'query.sort'],
            'reason' => 'Reference endpoints (forces, forces/{id}/people) return the whole collection with no page/filter/sort params; crime endpoints instead take lat/lng/date or polygons, a different shape entirely.',
        ],
        'eager nested people' => [
            'probe' => 'unsupported',
            'features' => ['eager.concurrent'],
            'reason' => 'The parent forces list cannot be limited (44 forces -> 44 requests to eager-load people for all of them); skipped to stay polite to the API.',
        ],
    ],
];
