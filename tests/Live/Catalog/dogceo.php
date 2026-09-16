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
            'reason' => 'breeds/list/all answers a top-level object keyed by breed ({"affenpinscher":[],"african":["wild"],...}, curl-verified); hydrate() strips the breed names via array_values() and hands each sub-breed value to Model::fill(). A JSON list decodes to plain int keys 0..n, and fill() passes those straight to isFillable(string $key) — which has a strict string parameter — so the second entry ("african":["wild"]) throws a TypeError before any attribute is ever set. read.list cannot complete here at all, not merely lose the key names.',
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
