<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\NhtsaVpic;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class CarMake extends Model
{
    protected ?string $endpoint = 'GetMakesForVehicleType/car';

    protected ?string $dataKey = 'Results';

    protected string $primaryKey = 'MakeId';
}

final class ModelsForMake extends Model
{
    protected ?string $endpoint = 'GetModelsForMakeId';

    protected ?string $dataKey = 'Results';

    protected string $primaryKey = 'Model_ID';
}

return [
    'name' => 'NHTSA vPIC',
    'docs' => 'https://vpic.nhtsa.dot.gov/api/',
    'base_uri' => 'https://vpic.nhtsa.dot.gov/api/vehicles/',
    'traits' => [
        'response' => 'Count + Message + SearchCriteria + Results',
        'pagination' => 'none',
        'filters' => 'RPC method + path params',
        'keys' => 'MakeId',
        'formats' => 'xml default, format=json',
        'errors' => 'unknown id -> 200 Count 0',
    ],
    'scenarios' => [
        'list car makes' => [
            'probe' => 'list',
            'model' => CarMake::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']),
            'min' => 100,
        ],
        'rpc paths' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'relation.nested', 'errors.not-found'],
            'reason' => 'The method name and id share one URL segment (GetModelsForMakeId/{id}) and the payload wraps a *list* of models under Results, not one object, so find() hydrates the whole list as a single model\'s attributes (getKey() then misses); a nested hasMany would need the same {parent}/{id}/{child} shape this API does not have; and an unknown id answers HTTP 200 with Count 0 instead of 404.',
            'attempt' => ['probe' => 'not-found', 'model' => ModelsForMake::class, 'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']), 'id' => 999999999],
        ],
    ],
];
