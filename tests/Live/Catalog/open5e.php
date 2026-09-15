<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Open5e;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

final class SpellV2 extends Model
{
    protected ?string $endpoint = 'v2/spells';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'key';
}

final class SpellV2List extends Model
{
    // Trailing slash: this is only ever fetched as a collection, and DRF's
    // APPEND_SLASH otherwise 301-redirects every request (see notes below).
    protected ?string $endpoint = 'v2/spells/';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'key';
}

final class SpellV1 extends Model
{
    protected ?string $endpoint = 'v1/spells/';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'slug';
}

final class DocumentV1 extends Model
{
    protected ?string $endpoint = 'v1/documents/';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'slug';

    public function spells(): HasMany
    {
        return $this->hasMany(SpellV1::class, 'document__slug')->batch();
    }

    public function spellsByDocument(): HasMany
    {
        return $this->hasMany(SpellV1::class, 'document__slug');
    }
}

return [
    'name' => 'Open5e',
    'docs' => 'https://api.open5e.com/',
    'base_uri' => 'https://api.open5e.com/',
    'traits' => [
        'response' => 'results + count/next/previous (DRF)',
        'pagination' => 'page + limit',
        'filters' => 'django field / field__op (level__gte); django __in only reliable on some fields',
        'sort' => 'ordering=-field',
        'keys' => 'slug (v1) / key (v2)',
        'relations' => 'v1 flat document__slug fk; v2 relations are embedded objects, not fks',
        'errors' => '404 {"detail"}',
        'quirks' => 'DRF APPEND_SLASH: single-item GETs 301-redirect (list endpoints here use a trailing-slash endpoint to avoid it)',
    ],
    'client' => [
        'query' => ['preset' => 'django', 'names' => ['limit' => 'limit', 'sort' => 'ordering']],
        'pagination' => ['preset' => 'django'],
    ],
    'scenarios' => [
        'list v2 spells' => ['probe' => 'list', 'model' => SpellV2List::class, 'min' => 50],
        'find v2 spell' => ['probe' => 'find', 'model' => SpellV2::class, 'id' => 'srd_fireball'],
        'filter level' => ['probe' => 'filter', 'model' => SpellV2List::class, 'field' => 'level', 'value' => 9, 'min' => 20],
        'where-in keys' => ['probe' => 'where-in', 'model' => SpellV2List::class, 'field' => 'key', 'values' => ['srd_fireball', 'srd_magic-missile']],
        'sort by level desc' => ['probe' => 'sort', 'model' => SpellV2List::class, 'field' => 'level', 'direction' => 'desc'],
        'paginate v2 spells' => ['probe' => 'paginate', 'model' => SpellV2List::class, 'per_page' => 20],
        'simple paginate v2 spells' => ['probe' => 'simple-paginate', 'model' => SpellV2List::class, 'per_page' => 20],
        'lazy walk v2 spells' => ['probe' => 'lazy', 'model' => SpellV2List::class, 'chunk' => 50, 'take' => 150],
        'batched document spells (v1)' => ['probe' => 'eager', 'model' => DocumentV1::class, 'relation' => 'spells', 'mode' => 'batch'],
        'concurrent document spells (v1)' => ['probe' => 'eager', 'model' => DocumentV1::class, 'query' => fn (Builder $query) => $query->limit(3), 'relation' => 'spellsByDocument', 'mode' => 'concurrent'],
        'missing spell' => ['probe' => 'not-found', 'model' => SpellV2::class, 'id' => 'doesnotexist'],
        'silently ignored lookups' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in'],
            'reason' => '__in is honoured only on some fields: v2 spells level__in and v1 documents slug__in are silently ignored, returning the unfiltered collection instead of an error, so a whereIn can look successful while filtering nothing.',
            'attempt' => ['probe' => 'where-in', 'model' => SpellV2List::class, 'field' => 'level', 'values' => [8, 9]],
        ],
        'v1 document detail' => [
            'probe' => 'unsupported',
            'features' => ['relation.has-many', 'relation.belongs-to'],
            'reason' => 'v1/documents/{slug} returns 404 (only the collection endpoint works), so has-many/belongs-to probes, which fetch the parent by key first, cannot run on v1; v2 relations are embedded objects, not flat fks.',
        ],
        'get many v2 spells' => [
            'probe' => 'unsupported',
            'features' => ['read.get-many'],
            'reason' => 'Every /v2/spells/{key} detail request 301-redirects to add the trailing slash DRF requires; the client follows it, but the redirect hop is recorded as a second request per id, so getMany\'s exact-request-count assertion (one request per id) cannot be satisfied even though the data loads correctly.',
            'attempt' => ['probe' => 'get-many', 'model' => SpellV2::class, 'ids' => ['srd_fireball', 'srd_magic-missile', 'srd_acid-arrow']],
        ],
    ],
];
