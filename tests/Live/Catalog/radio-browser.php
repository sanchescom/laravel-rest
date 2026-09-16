<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\RadioBrowser;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Station extends Model
{
    protected ?string $endpoint = 'stations/search';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'stationuuid';
}

final class StationByUuid extends Model
{
    protected ?string $endpoint = 'stations/byuuid';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'stationuuid';
}

return [
    'name' => 'Radio Browser',
    'docs' => 'https://api.radio-browser.info/',
    'base_uri' => 'https://de1.api.radio-browser.info/json/',
    'traits' => [
        'response' => 'bare-array',
        'pagination' => 'offset via offset + limit; no totals',
        'filters' => 'search params (countrycode, tag), byuuid?uuids=comma (comma-joined works here)',
        'sort' => 'order=field&reverse=true|false',
        'keys' => 'stationuuid',
    ],
    'client' => [
        'pagination' => ['style' => 'offset'],
    ],
    'scenarios' => [
        'where-in uuids' => [
            'probe' => 'where-in',
            'model' => StationByUuid::class,
            'field' => 'uuids',
            'attribute' => 'stationuuid',
            'values' => ['9606f727-0601-11e8-ae97-52543be04c81', '962dc110-0601-11e8-ae97-52543be04c81'],
        ],
        'list German stations' => [
            'probe' => 'list',
            'model' => Station::class,
            'query' => fn (Builder $query) => $query->withQuery(['countrycode' => 'DE'])->limit(50),
            'min' => 50,
        ],
        'lazy walk stations' => [
            'probe' => 'lazy',
            'model' => Station::class,
            'query' => fn (Builder $query) => $query->withQuery(['countrycode' => 'DE', 'order' => 'stationuuid']),
            'chunk' => 50,
            'take' => 150,
            'features' => ['paginate.style.offset'],
        ],
        'boolean direction' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'Direction is reverse=true|false; orderBy() renders sort=-votes (curl-verified: results not ordered by votes at all, since "sort" isn\'t a recognised param here), while order=votes&reverse=true curl-verified sorts correctly. Workaround: withQuery([\'order\'=>\'votes\',\'reverse\'=>\'true\']).',
            'attempt' => ['probe' => 'sort', 'model' => Station::class, 'query' => fn (Builder $query) => $query->withQuery(['countrycode' => 'DE'])->limit(20), 'field' => 'votes', 'direction' => 'desc'],
        ],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'No total count is returned anywhere in the response body.',
            'attempt' => ['probe' => 'paginate', 'model' => Station::class, 'query' => fn (Builder $query) => $query->withQuery(['countrycode' => 'DE']), 'per_page' => 20],
        ],
    ],
];
