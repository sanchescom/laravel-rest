<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\CratesIo;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

final class CrateSummary extends Model
{
    protected ?string $endpoint = 'crates';

    protected ?string $dataKey = 'crates';
}

final class Version extends Model
{
    protected ?string $endpoint = 'versions';

    protected ?string $dataKey = 'versions';
}

final class CrateDetail extends Model
{
    protected ?string $endpoint = 'crates';

    protected ?string $dataKey = 'crate';

    public function crateVersions(): HasMany
    {
        return $this->hasMany(Version::class)->nested();
    }
}

return [
    'name' => 'crates.io',
    'docs' => 'https://crates.io/data-access',
    'base_uri' => 'https://crates.io/api/v1/',
    'throttle_ms' => 1000,
    'traits' => [
        'response' => 'named-key:crates + meta{total,next_page} / named-key:crate detail',
        'pagination' => 'page + per_page',
        'total_location' => 'body:meta.total',
        'filters' => 'q=, category=, ids[]= (repeated only)',
        'sort' => 'sort=downloads|recent-downloads|alpha|new (bare keyword, implied direction)',
        'keys' => 'crate name',
        'errors' => '404 {"errors":[{"detail"}]}',
        'rate_limit' => 'crawler policy 1 req/s, UA required',
    ],
    'client' => [
        'query' => ['names' => ['limit' => 'per_page']],
        'pagination' => ['style' => 'page', 'total' => 'meta.total', 'next' => 'meta.next_page'],
    ],
    'scenarios' => [
        'list crates' => [
            'probe' => 'list',
            'model' => CrateSummary::class,
            'query' => fn (Builder $query) => $query->limit(25),
            'min' => 25,
        ],
        'paginate crates' => [
            'probe' => 'paginate',
            'model' => CrateSummary::class,
            'per_page' => 25,
        ],
        'find crate' => [
            'probe' => 'find',
            'model' => CrateDetail::class,
            'id' => 'itoa',
        ],
        'crate versions' => [
            'probe' => 'has-many',
            'model' => CrateDetail::class,
            'id' => 'itoa',
            'relation' => 'crateVersions',
            'nested' => true,
            'path_suffix' => 'crates/itoa/versions',
        ],
        'missing crate' => [
            'probe' => 'not-found',
            'model' => CrateDetail::class,
            'id' => 'doesnotexist-live-zzz',
        ],
        'bracket membership' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in'],
            'reason' => 'ids[]=serde&ids[]=rand (repeated) works, curl-verified 2 crates; the package\'s whereIn renders one comma-joined param (ids[]=serde,rand) instead, which curl-verified returns 0 crates.',
            'attempt' => ['probe' => 'where-in', 'model' => CrateSummary::class, 'field' => 'ids[]', 'attribute' => 'id', 'values' => ['serde', 'rand']],
        ],
        'keyword sort' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'sort takes a bare keyword with an implied direction (sort=downloads, curl-verified: properly desc by downloads); orderBy() renders sort=-downloads for desc, which curl-verified is ignored (falls back to the default order, not sorted by downloads at all).',
            'attempt' => ['probe' => 'sort', 'model' => CrateSummary::class, 'field' => 'downloads', 'direction' => 'desc'],
        ],
    ],
];
