<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Ibge;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

final class Estado extends Model
{
    protected ?string $endpoint = 'estados';

    protected ?string $dataKey = null;

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class)->nested();
    }
}

final class Municipio extends Model
{
    protected ?string $endpoint = 'municipios';

    protected ?string $dataKey = null;
}

return [
    'name' => 'IBGE Localidades',
    'docs' => 'https://servicodados.ibge.gov.br/api/docs/localidades',
    'base_uri' => 'https://servicodados.ibge.gov.br/api/v1/localidades/',
    'traits' => [
        'response' => 'bare-array / bare-object (gzip always)',
        'pagination' => 'none',
        'filters' => 'none (pipe-separated ids in path)',
        'sort' => 'orderBy=field (asc only)',
        'keys' => 'int id',
        'relations' => 'estados/{id}/municipios nested',
        'errors' => 'unknown id -> 200 []',
    ],
    'scenarios' => [
        'list states' => [
            'probe' => 'list',
            'model' => Estado::class,
            'min' => 27,
            'fields' => ['id', 'sigla'],
        ],
        'find state' => [
            'probe' => 'find',
            'model' => Estado::class,
            'id' => 33,
            'fields' => ['sigla'],
        ],
        'get many states' => [
            'probe' => 'get-many',
            'model' => Estado::class,
            'ids' => [33, 35, 31],
        ],
        'state municipalities' => [
            'probe' => 'has-many',
            'model' => Estado::class,
            'id' => 33,
            'relation' => 'municipios',
            'nested' => true,
            'path_suffix' => 'estados/33/municipios',
        ],
        'missing state' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'Unknown id returns HTTP 200 with [] instead of a 404, so ModelNotFoundException never fires.',
            'attempt' => ['probe' => 'not-found', 'model' => Estado::class, 'id' => 9999],
        ],
        'sort' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'orderBy=nome only accepts a field name, never a direction, and server collation of accented names (Pará, Paraíba, Paraná) differs from PHP byte order, so a descending orderBy() cannot be verified as reversed.',
        ],
        'multi ids' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in'],
            'reason' => 'Multiple ids are pipe-separated path segments (estados/33|35), not a query parameter; whereIn() renders id=33,35 as a query string, which the API ignores. Workaround: from(\'estados/33|35\').',
            'attempt' => ['probe' => 'where-in', 'model' => Estado::class, 'field' => 'id', 'values' => [33, 35]],
        ],
    ],
];
