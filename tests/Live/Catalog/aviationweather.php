<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Aviationweather;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Metar extends Model
{
    protected ?string $endpoint = 'metar';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'icaoId';
}

return [
    'name' => 'AviationWeather.gov Data API',
    'docs' => 'https://aviationweather.gov/data/api/',
    'base_uri' => 'https://aviationweather.gov/api/data/',
    'traits' => [
        'response' => 'bare-array (format=json); raw METAR text is the default (no format param)',
        'pagination' => 'none',
        'filters' => 'ids=comma list',
        'keys' => 'icaoId',
    ],
    'scenarios' => [
        'where-in stations' => [
            'probe' => 'where-in',
            'model' => Metar::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json']),
            'field' => 'ids',
            'attribute' => 'icaoId',
            'values' => ['KJFK', 'KLAX', 'KORD'],
        ],
        'list metars' => [
            'probe' => 'list',
            'model' => Metar::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'json', 'ids' => 'KJFK,KLAX']),
            'min' => 2,
        ],
        'default format is raw text' => [
            'probe' => 'unsupported',
            'features' => ['read.list'],
            'reason' => 'Without format=json the response is a raw METAR text line, not JSON, so decoding it fails outright.',
            'attempt' => ['probe' => 'list', 'model' => Metar::class, 'query' => fn (Builder $query) => $query->withQuery(['ids' => 'KJFK'])],
        ],
        'no detail endpoint or pagination' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'paginate.lazy'],
            'reason' => 'There is no path-based detail route: GET metar/KJFK 404s ("Not found") even though the station exists in the ids= list form; the API has no total/next for lazy() either.',
            'attempt' => ['probe' => 'find', 'model' => Metar::class, 'id' => 'KJFK'],
        ],
    ],
];
