<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Catfact;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class Fact extends Model
{
    protected ?string $endpoint = 'facts';

    protected ?string $dataKey = 'data';

    protected string $primaryKey = 'fact';
}

final class FactLaravelPreset extends Model
{
    protected ?string $endpoint = 'facts';

    protected ?string $dataKey = 'data';

    protected string $primaryKey = 'fact';

    protected array|string|null $pagination = 'laravel';
}

return [
    'name' => 'Cat Facts',
    'docs' => 'https://catfact.ninja/',
    'base_uri' => 'https://catfact.ninja/',
    'traits' => [
        'response' => 'flat Laravel paginator JSON (data, total, next_page_url, links[])',
        'pagination' => 'page + limit; total in body:total; next in body:next_page_url',
        'keys' => 'none (fact text only, used as primaryKey)',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'total', 'next' => 'next_page_url'],
    ],
    'scenarios' => [
        'list facts' => [
            'probe' => 'list',
            'model' => Fact::class,
            'min' => 10,
        ],
        'paginate facts' => [
            'probe' => 'paginate',
            'model' => Fact::class,
            'per_page' => 25,
        ],
        'simple paginate facts' => [
            'probe' => 'simple-paginate',
            'model' => Fact::class,
            'per_page' => 25,
            'last_page' => 14,
        ],
        'lazy walk facts' => [
            'probe' => 'custom',
            'features' => ['paginate.lazy'],
            'run' => function (LiveContext $context) {
                // The shared lazy probe requires unique keys across the walk, but fact
                // text genuinely repeats in this dataset (curl-verified: only 296 unique
                // among 300 facts across 3 pages) and there is no id to key on instead.
                // This verifies the walk mechanics directly: every page fetched, no gaps.
                $count = 0;
                $texts = [];

                foreach ((new Fact)->newBuilder()->lazy(100)->take(300) as $model) {
                    $count++;
                    $texts[$model->getKey()] = true;
                }

                expect($count)->toBe(300)
                    ->and($context->requests())->toBe(3)
                    ->and(count($texts))->toBeGreaterThan(280);
            },
        ],
        'response cache' => [
            'probe' => 'cache',
            'model' => Fact::class,
            'kind' => 'response',
        ],
        'detail' => [
            'probe' => 'unsupported',
            'features' => ['read.find'],
            'reason' => 'Facts have no ids or a detail endpoint to fetch a single one by key.',
        ],
        'laravel preset' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => "The raw paginator is flat (total, next_page_url), not the 'laravel' preset's meta.total/links.next shape; the preset would read null and paginate() would report a total of 0. Configuring total/next explicitly (as this catalog does) works instead.",
            'attempt' => ['probe' => 'paginate', 'model' => FactLaravelPreset::class, 'per_page' => 25],
        ],
    ],
];
