<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openmeteo;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Forecast extends Model
{
    protected ?string $endpoint = 'forecast';

    protected ?string $dataKey = null;
}

final class HourlyTemperatures extends Model
{
    protected ?string $endpoint = 'forecast';

    protected ?string $dataKey = 'hourly.temperature_2m';
}

return [
    'name' => 'Open-Meteo',
    'docs' => 'https://open-meteo.com/en/docs',
    'base_uri' => 'https://api.open-meteo.com/v1/',
    'traits' => [
        'response' => 'bare-object with parallel arrays (hourly.time[], hourly.temperature_2m[])',
        'pagination' => 'none',
        'filters' => 'latitude/longitude + variable lists',
        'errors' => '400 {"error":true,"reason"}',
        'rate_limit' => 'documented 10k/day non-commercial',
    ],
    'scenarios' => [
        'invalid latitude' => [
            'probe' => 'status',
            'model' => Forecast::class,
            'query' => fn (Builder $query) => $query->withQuery(['latitude' => 999, 'longitude' => 0, 'hourly' => 'temperature_2m']),
            'status' => 400,
        ],
        'parallel arrays' => [
            'probe' => 'unsupported',
            'features' => ['read.list'],
            'reason' => 'Time series come back as column-oriented parallel arrays (hourly.time[] and hourly.temperature_2m[] zipped by index), not row objects; pointing dataKey at the value column hydrates each bare float into an attribute-less model instead of a per-hour row.',
            'attempt' => ['probe' => 'list', 'model' => HourlyTemperatures::class, 'query' => fn (Builder $query) => $query->withQuery(['latitude' => 52.52, 'longitude' => 13.41, 'hourly' => 'temperature_2m']), 'min' => 5, 'fields' => ['temperature_2m']],
        ],
    ],
];
