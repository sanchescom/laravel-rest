<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\DrupalJsonapi;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class ProjectModule extends Model
{
    protected ?string $endpoint = 'node/project_module';

    protected ?string $dataKey = 'data';
}

return [
    'name' => 'Drupal.org JSON:API',
    'docs' => 'https://www.drupal.org/drupalorg/docs/apis/jsonapi',
    'base_uri' => 'https://new.drupal.org/jsonapi/',
    'traits' => [
        'response' => 'jsonapi',
        'pagination' => 'offset via page[limit] + page[offset]; no meta.count',
        'filters' => 'filter[field]=value; exact-match filters are unreliable on a changing dataset',
        'sort' => 'sort=-field',
        'keys' => 'uuid',
        'relations' => 'relationships links',
        'errors' => 'jsonapi errors[] (unknown uuid observed timing out)',
    ],
    'client' => [
        'query' => ['preset' => 'jsonapi', 'names' => ['limit' => 'page.limit', 'offset' => 'page.offset']],
        'pagination' => ['style' => 'offset', 'next' => 'links.next.href'],
    ],
    'scenarios' => [
        'list modules' => ['probe' => 'list', 'model' => ProjectModule::class, 'min' => 10],
        'sort by created' => ['probe' => 'sort', 'model' => ProjectModule::class, 'query' => fn (Builder $query) => $query->limit(10), 'field' => 'created', 'attribute' => 'attributes.created', 'direction' => 'desc'],
        'filter by title' => ['probe' => 'filter', 'model' => ProjectModule::class, 'field' => 'title', 'attribute' => 'attributes.title', 'value' => 'Amazon Product Advertisement API', 'min' => 1],
        'simple paginate modules' => ['probe' => 'simple-paginate', 'model' => ProjectModule::class, 'per_page' => 10],
        'find module' => ['probe' => 'find', 'model' => ProjectModule::class, 'id' => '25fa671a-ea22-4def-96ed-a6b009fb52cd'],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'Drupal omits meta.count by default, so paginate() has no total to read.',
            'attempt' => ['probe' => 'paginate', 'model' => ProjectModule::class, 'per_page' => 10],
        ],
        'missing module' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'An unknown uuid request does not respond (times out after 20s+) instead of returning 404; not reliable enough for a probe, and an attempt here would hang every live run.',
        ],
        'lazy walk modules' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => "Every page silently omits some rows the current session can't view (meta.omitted 'insufficient authorization'), so a page[limit]=10 request returns fewer than 10 items (observed 5, 9, 8); lazy()'s exact-request-count assumption (a full page until the last one) never holds on this endpoint.",
            'attempt' => ['probe' => 'lazy', 'model' => ProjectModule::class, 'chunk' => 10, 'take' => 30],
        ],
    ],
];
