<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Represent;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class Representative extends Model
{
    // Trailing slash: the API 301-redirects a request without it.
    protected ?string $endpoint = 'representatives/';

    protected ?string $dataKey = 'objects';

    protected string $primaryKey = 'url';
}

final class BoundarySet extends Model
{
    protected ?string $endpoint = 'boundary-sets/';

    protected ?string $dataKey = 'objects';

    protected string $primaryKey = 'url';
}

return [
    'name' => 'Represent (Open North)',
    'docs' => 'https://represent.opennorth.ca/api/',
    'base_uri' => 'https://represent.opennorth.ca/',
    'traits' => [
        'response' => 'objects + meta{offset,limit,total_count,next,previous} (Tastypie-like)',
        'pagination' => 'limit + offset; total in meta.total_count',
        'filters' => 'field=value, field__istartswith (django-style lookups)',
        'sort' => 'none',
        'keys' => 'none; resources are identified by url path strings',
        'errors' => 'an unrecognised sub-path answers 200 with empty objects, not 404',
    ],
    'client' => [
        'query' => [
            'preset' => 'django',
            // The django preset defaults limit to page_size; override back to this API's real param names.
            'names' => ['limit' => 'limit', 'offset' => 'offset'],
        ],
        'pagination' => ['style' => 'offset', 'total' => 'meta.total_count', 'next' => 'meta.next'],
    ],
    'scenarios' => [
        'list representatives' => ['probe' => 'list', 'model' => Representative::class, 'min' => 20],
        'filter MPs' => ['probe' => 'filter', 'model' => Representative::class, 'field' => 'elected_office', 'value' => 'MP', 'min' => 20],
        'paginate MPs' => [
            'probe' => 'paginate',
            'model' => Representative::class,
            'query' => fn (Builder $query) => $query->where('elected_office', 'MP'),
            'per_page' => 20,
        ],
        'simple paginate boundary sets' => ['probe' => 'simple-paginate', 'model' => BoundarySet::class, 'per_page' => 20],
        'lazy walk boundary sets' => ['probe' => 'lazy', 'model' => BoundarySet::class, 'chunk' => 50, 'take' => 150],
        'membership' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in'],
            'reason' => 'elected_office__in=MP,MPP is silently ignored (unfiltered results returned).',
            'attempt' => ['probe' => 'where-in', 'model' => Representative::class, 'field' => 'elected_office', 'values' => ['MP', 'MPP']],
        ],
        'ids' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'read.get-many', 'errors.not-found'],
            'reason' => 'Resources are identified by url path strings (/boundary-sets/federal-electoral-districts/) with no id attribute. get(id) requests representatives/{id}, which 301-redirects (APPEND_SLASH) to representatives/{id}/ and answers 200 {"objects":[],"meta":{"total_count":0}} — a valid but keyless empty response, not a match and never a 404 — so read.find has nothing to compare against and errors.not-found never fires.',
            'attempt' => ['probe' => 'find', 'model' => Representative::class, 'query' => fn (Builder $query) => $query->from('representatives'), 'id' => 'doesnotexist'],
        ],
        'django lookups' => [
            'probe' => 'custom',
            'features' => ['query.filter'],
            'run' => function (LiveContext $context) {
                $models = (new Representative)->newBuilder()->where('last_name', 'istartswith', 'Ab')->get();

                expect($models->count())->toBeGreaterThanOrEqual(1);

                foreach ($models as $model) {
                    expect(str_starts_with(strtolower((string) $model->getAttribute('last_name')), 'ab'))->toBeTrue();
                }
            },
        ],
    ],
];
