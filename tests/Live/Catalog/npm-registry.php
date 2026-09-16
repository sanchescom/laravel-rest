<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\NpmRegistry;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Package extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'name';
}

final class PackageSearchResult extends Model
{
    protected ?string $endpoint = '-/v1/search';

    protected ?string $dataKey = 'objects';

    protected string $primaryKey = 'name';
}

return [
    'name' => 'npm Registry',
    'docs' => 'https://github.com/npm/registry/blob/main/docs/REGISTRY-API.md',
    'base_uri' => 'https://registry.npmjs.org/',
    'traits' => [
        'response' => 'objects[{package,...}] + total (search) / packument bare-object',
        'pagination' => 'offset via from + size',
        'filters' => 'text= with qualifiers',
        'keys' => 'package name (root-level resources: endpoint "" + id)',
        'errors' => '404 {"error":"Not found"}',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'size', 'offset' => 'from']],
        'pagination' => ['style' => 'offset', 'total' => 'total'],
    ],
    'scenarios' => [
        'search packages' => [
            'probe' => 'list',
            'model' => PackageSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['text' => 'laravel'])->limit(20),
            'min' => 20,
            'fields' => ['package.name'],
        ],
        'paginate search' => [
            'probe' => 'paginate',
            'model' => PackageSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['text' => 'laravel']),
            'per_page' => 20,
        ],
        'find package' => [
            'probe' => 'find',
            'model' => Package::class,
            'id' => 'left-pad',
        ],
        'find scoped package' => [
            'probe' => 'find',
            'model' => Package::class,
            'id' => '@types/node',
        ],
        'get many packages' => [
            'probe' => 'get-many',
            'model' => Package::class,
            'ids' => ['left-pad', 'is-odd', 'is-even'],
        ],
        'missing package' => [
            'probe' => 'not-found',
            'model' => Package::class,
            'id' => 'doesnotexist-live-zzz',
        ],
        'nested item keys' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => 'Search result items carry their identity under a nested package.name; the item\'s own top-level keys are only package, score, searchScore, flags — there is no flat "name" attribute, so getKey() is null for every hit and lazy() cannot dedupe pages by key.',
            'attempt' => ['probe' => 'lazy', 'model' => PackageSearchResult::class, 'query' => fn (Builder $query) => $query->withQuery(['text' => 'laravel']), 'chunk' => 20, 'take' => 40],
        ],
    ],
];
