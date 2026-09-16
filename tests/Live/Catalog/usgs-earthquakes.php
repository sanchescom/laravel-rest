<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\UsgsEarthquakes;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Feature extends Model
{
    protected ?string $endpoint = 'query';

    protected ?string $dataKey = 'features';
}

return [
    'name' => 'USGS Earthquake Catalog (FDSN)',
    'docs' => 'https://earthquake.usgs.gov/fdsnws/event/1/',
    'base_uri' => 'https://earthquake.usgs.gov/fdsnws/event/1/',
    'traits' => [
        'response' => 'geojson FeatureCollection (features[].properties)',
        'pagination' => 'offset (1-based); limit+offset',
        'filters' => 'named range params (minmagnitude, starttime)',
        'sort' => 'orderby=time|time-asc|magnitude|magnitude-asc',
        'formats' => 'geojson, csv, xml, kml, text via format=',
        'errors' => '400 text/plain',
    ],
    'scenarios' => [
        'list features' => [
            'probe' => 'list',
            'model' => Feature::class,
            'query' => fn (Builder $query) => $query->withQuery([
                'format' => 'geojson',
                'minmagnitude' => 6,
                'starttime' => '2025-01-01',
                'orderby' => 'magnitude',
            ])->limit(10),
            'min' => 10,
            'fields' => ['properties.mag'],
        ],
        'invalid limit' => [
            'probe' => 'status',
            'model' => Feature::class,
            'query' => fn (Builder $query) => $query->withQuery(['format' => 'geojson', 'limit' => 'abc']),
            'status' => 400,
        ],
        'one-based offset' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy', 'paginate.simple'],
            'client' => ['pagination' => ['style' => 'offset']],
            'reason' => 'offset must be >= 1; the offset pagination style always sends offset=0 on the first page, which the API rejects (curl-verified 400 for offset=0, valid from offset=1).',
            'attempt' => [
                'probe' => 'simple-paginate',
                'model' => Feature::class,
                'query' => fn (Builder $query) => $query->withQuery(['format' => 'geojson', 'minmagnitude' => 6]),
                'per_page' => 5,
            ],
        ],
        'csv option is not decodable' => [
            'probe' => 'unsupported',
            'features' => ['read.list'],
            'reason' => 'format=csv answers text/csv from the same endpoint (curl-verified content-type); only format=geojson is JSON-decodable.',
            'attempt' => ['probe' => 'list', 'model' => Feature::class, 'query' => fn (Builder $query) => $query->withQuery(['format' => 'csv', 'minmagnitude' => 6, 'limit' => 5])],
        ],
        'sort direction in value' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'Direction is encoded in the orderby value itself (magnitude vs magnitude-asc); orderBy() renders a separate direction the API does not read, so the default grammar cannot express descending vs ascending here.',
        ],
    ],
];
