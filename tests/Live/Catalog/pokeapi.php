<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Pokeapi;

use Sanchescom\Rest\Model;

final class Pokemon extends Model
{
    protected ?string $endpoint = 'pokemon';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'name';
}

final class PokemonDetail extends Model
{
    protected ?string $endpoint = 'pokemon';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'name';
}

return [
    'name' => 'PokéAPI',
    'docs' => 'https://pokeapi.co/docs/v2',
    'base_uri' => 'https://pokeapi.co/api/v2/',
    'traits' => [
        'response' => 'results envelope with count / next / previous',
        'pagination' => 'offset + limit, absolute next URL',
        'keys' => 'name or integer id in the path',
        'filters' => 'none on list endpoints',
        'detail' => 'large nested objects',
    ],
    'client' => [
        'pagination' => ['style' => 'offset', 'total' => 'count', 'next' => 'next'],
    ],
    'scenarios' => [
        'list pokemon' => ['probe' => 'list', 'model' => Pokemon::class, 'min' => 20, 'fields' => ['name', 'url'], 'features' => ['grammar.plain']],
        'find pokemon by name' => ['probe' => 'find', 'model' => PokemonDetail::class, 'id' => 'pikachu', 'fields' => ['id', 'types']],
        'get many pokemon' => ['probe' => 'get-many', 'model' => PokemonDetail::class, 'ids' => ['bulbasaur', 'ivysaur', 'venusaur']],
        'paginate pokemon' => ['probe' => 'paginate', 'model' => Pokemon::class, 'per_page' => 50],
        'simple paginate pokemon' => ['probe' => 'simple-paginate', 'model' => Pokemon::class, 'per_page' => 50],
        'lazy walk pokemon' => ['probe' => 'lazy', 'model' => Pokemon::class, 'chunk' => 20, 'take' => 60],
        'missing pokemon' => ['probe' => 'not-found', 'model' => PokemonDetail::class, 'id' => 'definitely-not-a-pokemon'],
        'list filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'query.sort'],
            'reason' => 'PokéAPI list endpoints accept only offset and limit.',
        ],
    ],
];
