<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\NhtsaVpic;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

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
            'reason' => 'The method name and id share one URL segment (GetModelsForMakeId/{id}) and the payload wraps a *list* of models under Results, not one object, so find() hydrates the whole list as a single model\'s attributes (getKey() then misses). The attempt covers the error half: an unknown id answers HTTP 200 with Count 0 instead of 404. relation.nested is a URL-shape fact rather than an exercised call — a nested hasMany would need the {parent}/{id}/{child} route this RPC API does not have.',
            'attempt' => ['probe' => 'not-found', 'model' => ModelsForMake::class, 'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']), 'id' => 999999999],
        ],
        'rpc detail via from()' => [
            'probe' => 'custom',
            'features' => ['read.list', 'read.data-key'],
            'run' => function (LiveContext $context) {
                // from() is the package's workaround for a method-plus-id path segment
                // that get($id) cannot render: the whole RPC call becomes the endpoint,
                // and the Results list hydrates normally from there.
                $models = (new ModelsForMake)->newBuilder()
                    ->from('GetModelsForMakeId/440')
                    ->withQuery(['format' => 'json'])
                    ->get();

                $makeIds = $models->map(fn (Model $model) => $model->getAttribute('Make_ID'))->unique()->values()->all();

                expect($models->count())->toBeGreaterThanOrEqual(10)
                    ->and($makeIds)->toBe([440])
                    ->and($models->first()->getAttribute('Model_Name'))->not->toBeNull()
                    ->and($context->requests())->toBe(1);
            },
        ],
    ],
];
