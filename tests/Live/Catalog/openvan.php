<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openvan;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class EventList extends Model
{
    protected ?string $endpoint = 'events';

    protected ?string $dataKey = 'events';
}

final class Event extends Model
{
    protected ?string $endpoint = 'event';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'slug';
}

return [
    'name' => 'OpenVan.camp',
    'docs' => 'https://openvan.camp/en/developers',
    'base_uri' => 'https://openvan.camp/api/',
    'traits' => [
        'response' => 'named-key:events (list) / bare-object (event/{slug})',
        'pagination' => 'page (validated)',
        'filters' => 'locale=',
        'keys' => 'slug',
        'errors' => 'Laravel 422 {"message","errors":{field:[..]}}; 404 JSON',
    ],
    'scenarios' => [
        'list events' => [
            'probe' => 'list',
            'model' => EventList::class,
            'query' => fn (Builder $query) => $query->withQuery(['locale' => 'en']),
            'min' => 1,
        ],
        'invalid page' => [
            'probe' => 'status',
            'model' => EventList::class,
            'query' => fn (Builder $query) => $query->withQuery(['page' => 'abc']),
            'status' => 422,
            'errors' => ['page'],
        ],
        'find event' => [
            'probe' => 'find',
            'model' => Event::class,
            'id' => 'salon-des-vehicules-de-loisirs-au-coeur-de-la-2026',
            'fields' => ['event_name'],
        ],
        'missing event' => [
            'probe' => 'not-found',
            'model' => Event::class,
            'id' => 'doesnotexist-live',
        ],
    ],
];
