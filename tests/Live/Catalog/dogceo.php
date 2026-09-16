<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Dogceo;

use Sanchescom\Rest\Model;

final class BreedListEntry extends Model
{
    protected ?string $endpoint = 'breed';

    protected ?string $dataKey = null;
}

final class BreedMap extends Model
{
    protected ?string $endpoint = 'breeds/list/all';

    protected ?string $dataKey = 'message';
}

final class SubBreedList extends Model
{
    protected ?string $endpoint = 'breed/hound/list';

    protected ?string $dataKey = 'message';
}

return [
    'name' => 'Dog CEO',
    'docs' => 'https://dog.ceo/dog-api/documentation/',
    'base_uri' => 'https://dog.ceo/api/',
    'traits' => [
        'response' => 'named-key:message (object map or string list) + status',
        'pagination' => 'none',
        'keys' => 'breed name as path segment',
        'errors' => '404 {"status":"error","message","code":404}',
    ],
    'scenarios' => [
        'missing breed' => [
            'probe' => 'not-found',
            'model' => BreedListEntry::class,
            'id' => 'doesnotexistlive/list',
        ],
        'breed map loses key names' => [
            'probe' => 'unsupported',
            'features' => ['read.list'],
            'reason' => 'breeds/list/all answers a top-level object keyed by breed ({"affenpinscher":[],"african":["wild"],...}); hydrate() strips the keys via array_values() and hydrates each sub-breed array as attributes with no breed name attached, so the first entry (an empty array) yields a model with no attributes at all.',
            'attempt' => ['probe' => 'list', 'model' => BreedMap::class, 'min' => 100, 'fields' => ['0']],
        ],
        'sub-breed string lists hydrate empty' => [
            'probe' => 'unsupported',
            'features' => ['read.list', 'relation.nested'],
            'reason' => 'breed/{name}/list answers a bare array of strings (["afghan","basset",...]); hydrate() only keeps array items, so every string becomes an attribute-less model. The same shape would break a nested hasMany relation onto this endpoint.',
            'attempt' => ['probe' => 'list', 'model' => SubBreedList::class, 'min' => 5, 'fields' => ['0']],
        ],
    ],
];
