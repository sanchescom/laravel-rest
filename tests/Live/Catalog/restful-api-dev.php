<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\RestfulApiDev;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class ObjectModel extends Model
{
    protected ?string $endpoint = 'objects';

    protected ?string $dataKey = null;

    public function same(): BelongsTo
    {
        return $this->belongsTo(ObjectModel::class, 'id')->batch();
    }
}

return [
    'name' => 'restful-api.dev',
    'docs' => 'https://restful-api.dev/',
    'base_uri' => 'https://api.restful-api.dev/',
    'throttle_ms' => 500,
    'traits' => [
        'response' => 'bare-array / bare-object',
        'pagination' => 'none (13 seed objects)',
        'filters' => 'id=a,b (comma) or repeated id; no generic field filter',
        'keys' => 'string id (seed "1".."13", created hex)',
        'writes' => 'persisted; seed ids reserved (405)',
        'errors' => '404 {"error"}, 405 reserved id',
    ],
    'scenarios' => [
        'list objects' => ['probe' => 'list', 'model' => ObjectModel::class, 'min' => 13, 'fields' => ['id', 'name']],
        'find object' => ['probe' => 'find', 'model' => ObjectModel::class, 'id' => '7', 'fields' => ['name']],
        'get many objects' => ['probe' => 'get-many', 'model' => ObjectModel::class, 'ids' => ['1', '2', '3']],
        'where-in ids' => ['probe' => 'where-in', 'model' => ObjectModel::class, 'field' => 'id', 'values' => ['3', '5', '10']],
        'missing object' => ['probe' => 'not-found', 'model' => ObjectModel::class, 'id' => 999999],
        'write lifecycle' => [
            'probe' => 'custom',
            'features' => ['write.create', 'write.update', 'write.patch', 'write.delete'],
            'run' => function (LiveContext $context) {
                $created = (new ObjectModel)->newBuilder()->post(['name' => 'laravel-rest live', 'data' => ['check' => 1]]);

                expect($created)->not->toBeNull();

                $id = $created->getKey();

                expect($id)->not->toBeNull();

                new LiveContext($context->slug, $context->api, ['update_method' => 'patch']);

                $patched = (new ObjectModel)->newBuilder()->put($id, ['name' => 'patched']);

                expect($patched)->not->toBeNull()
                    ->and($patched->getAttribute('name'))->toBe('patched');

                new LiveContext($context->slug, $context->api);

                $put = (new ObjectModel)->newBuilder()->put($id, ['name' => 'put', 'data' => ['check' => 2]]);

                expect($put)->not->toBeNull()
                    ->and($put->getAttribute('name'))->toBe('put');

                expect((new ObjectModel)->newBuilder()->delete($id))->toBeTrue();
            },
        ],
        'reserved id write' => [
            'probe' => 'custom',
            'features' => ['errors.client'],
            'run' => function (LiveContext $context) {
                $error = null;

                try {
                    (new ObjectModel)->newBuilder()->put('7', ['name' => 'x']);
                } catch (RequestException $exception) {
                    $error = $exception;
                }

                expect($error)->not->toBeNull();
                expect($error->status)->toBe(405);
            },
        ],
        'batch self relation (synthetic)' => [
            'probe' => 'eager',
            'model' => ObjectModel::class,
            'query' => fn (Builder $query) => $query->whereIn('id', ['3', '5']),
            'relation' => 'same',
            'mode' => 'batch',
        ],
        'pagination/sort/filters' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.simple', 'paginate.lazy', 'query.sort', 'query.filter'],
            'reason' => 'Whole collection is returned in one response; page params are ignored; only an id-membership filter exists (no generic field=value filter).',
            'attempt' => ['probe' => 'filter', 'model' => ObjectModel::class, 'field' => 'name', 'value' => 'definitely-not-a-real-name'],
        ],
    ],
];
