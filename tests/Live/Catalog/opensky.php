<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Opensky;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class State extends Model
{
    protected ?string $endpoint = 'states/all';

    protected ?string $dataKey = 'states';
}

return [
    'name' => 'OpenSky Network',
    'docs' => 'https://openskynetwork.github.io/opensky-api/rest.html',
    'base_uri' => 'https://opensky-network.org/api/',
    'traits' => [
        'response' => 'states: array of positional arrays, index 0..16 (icao24, callsign, ...)',
        'filters' => 'bbox lamin/lomin/lamax/lomax',
        'rate_limit' => 'anonymous credit budget (~400/day)',
        'errors' => 'no bbox match -> states:null',
    ],
    'scenarios' => [
        'positional rows crash hydration' => [
            'probe' => 'unsupported',
            'features' => ['read.list', 'read.find', 'query.filter'],
            'reason' => 'Each state is a positional array with no field names ([icao24, callsign, origin_country, ...], curl-verified); json_decode gives it plain int keys 0..16, and Model::fill() passes those straight to isFillable(string $key) — which has a strict string parameter — so hydrating even one row throws a TypeError before any attribute is ever set. list()/find()/where() are all impossible here, not merely attribute-less: the request never completes.',
            'attempt' => [
                'probe' => 'list',
                'model' => State::class,
                'query' => fn (Builder $query) => $query->withQuery(['lamin' => 45.8, 'lomin' => 5.99, 'lamax' => 47.8, 'lomax' => 10.5]),
            ],
        ],
    ],
];
