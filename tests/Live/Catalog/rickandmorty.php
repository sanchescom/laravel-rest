<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Rickandmorty;

use Sanchescom\Rest\Model;

final class Character extends Model
{
    protected ?string $endpoint = 'character';

    protected ?string $dataKey = null;
}

final class CharacterList extends Model
{
    protected ?string $endpoint = 'character';

    protected ?string $dataKey = 'results';
}

final class Episode extends Model
{
    protected ?string $endpoint = 'episode';

    protected ?string $dataKey = 'results';
}

return [
    'name' => 'Rick and Morty API',
    'docs' => 'https://rickandmortyapi.com/documentation',
    'base_uri' => 'https://rickandmortyapi.com/api/',
    'traits' => [
        'response' => 'results + info{count,pages,next,prev}',
        'pagination' => 'page (fixed page size 20); total in info.count',
        'filters' => 'field=value',
        'sort' => 'none',
        'keys' => 'int id',
        'relations' => 'absolute URL strings (origin.url, episode[]), no fk ids',
        'errors' => '404 {"error"}',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'info.count', 'next' => 'info.next'],
    ],
    'scenarios' => [
        'list characters' => ['probe' => 'list', 'model' => CharacterList::class, 'min' => 20],
        'find character' => ['probe' => 'find', 'model' => Character::class, 'id' => 1, 'fields' => ['name']],
        'get many characters' => ['probe' => 'get-many', 'model' => Character::class, 'ids' => [1, 2, 3]],
        'filter status' => ['probe' => 'filter', 'model' => CharacterList::class, 'field' => 'status', 'value' => 'Dead', 'min' => 20],
        'paginate characters' => ['probe' => 'paginate', 'model' => CharacterList::class, 'per_page' => 20],
        'simple paginate episodes' => ['probe' => 'simple-paginate', 'model' => Episode::class, 'per_page' => 20, 'last_page' => 3],
        'lazy walk episodes' => ['probe' => 'lazy', 'model' => Episode::class, 'chunk' => 20, 'take' => 51, 'expect_count' => 51],
        'missing character' => ['probe' => 'not-found', 'model' => Character::class, 'id' => 99999],
        'request memo' => ['probe' => 'cache', 'kind' => 'memo', 'model' => Character::class, 'id' => 1],
        'url relations' => [
            'probe' => 'unsupported',
            'features' => ['relation.belongs-to', 'relation.has-many'],
            'reason' => 'Relations are absolute URL strings (origin.url, location.url, episode[]) with no fk id attribute for belongsTo/hasMany to read.',
        ],
        'sort' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'No sort parameter; sort=name is silently ignored (curl-verified: same unsorted, unfiltered order regardless of direction).',
            'attempt' => ['probe' => 'sort', 'model' => CharacterList::class, 'field' => 'name', 'direction' => 'asc'],
        ],
        'multi-get' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in'],
            'reason' => 'Batch fetch is a path list (character/1,2,3), not a query param; id=1,2,3 is silently ignored (curl-verified: full unfiltered page returned).',
            'attempt' => ['probe' => 'where-in', 'model' => CharacterList::class, 'field' => 'id', 'values' => [1, 2, 3]],
        ],
    ],
];
