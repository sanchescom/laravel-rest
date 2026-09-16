<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Scryfall;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class SetModel extends Model
{
    protected ?string $endpoint = 'sets';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'code';
}

final class CardSearchResult extends Model
{
    protected ?string $endpoint = 'cards/search';

    protected ?string $dataKey = 'data';
}

return [
    'name' => 'Scryfall',
    'docs' => 'https://scryfall.com/docs/api',
    'base_uri' => 'https://api.scryfall.com/',
    'traits' => [
        'response' => 'object:list data + has_more + total_cards; detail bare object',
        'pagination' => 'page (fixed 175 per page)',
        'filters' => 'q= search DSL only; bare field=value params are ignored',
        'sort' => 'order=field&dir=asc|desc',
        'keys' => 'set code / uuid',
        'headers' => 'Accept and User-Agent must be present with any value (400 only when a header is fully absent, curl-verified); the harness always sends both by default',
        'errors' => '404/400 {"object":"error","status","details"}',
        'rate_limit' => 'documented ~10 req/s',
    ],
    'throttle_ms' => 100,
    'client' => [
        'query' => [
            'sort' => 'separate',
            'sort_names' => ['field' => 'order', 'direction' => 'dir'],
        ],
        'pagination' => ['style' => 'page', 'total' => 'total_cards', 'has_more' => 'has_more'],
    ],
    'scenarios' => [
        'explicit accept header' => [
            'probe' => 'headers',
            'model' => SetModel::class,
            'id' => 'khm',
            'headers' => ['Accept' => 'application/json;q=0.9'],
            'echo' => false,
        ],
        'find set' => [
            'probe' => 'find',
            'model' => SetModel::class,
            'id' => 'khm',
            'fields' => ['name'],
        ],
        'get many sets' => [
            'probe' => 'get-many',
            'model' => SetModel::class,
            'ids' => ['khm', 'neo', 'dmu'],
        ],
        'sort gods by cmc' => [
            'probe' => 'sort',
            'model' => CardSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'set:khm type:god']),
            'field' => 'cmc',
            'direction' => 'desc',
        ],
        'simple paginate by has_more' => [
            'probe' => 'simple-paginate',
            'model' => CardSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'set:khm']),
            'per_page' => 175,
            'last_page' => 2,
        ],
        'paginate by total_cards' => [
            'probe' => 'paginate',
            'model' => CardSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => 'set:khm']),
            'per_page' => 175,
        ],
        'missing set' => [
            'probe' => 'not-found',
            'model' => SetModel::class,
            'id' => 'doesnotexistlive',
        ],
        'empty search' => [
            'probe' => 'status',
            'model' => CardSearchResult::class,
            'query' => fn (Builder $query) => $query->withQuery(['q' => '']),
            'status' => 400,
        ],
        'page size' => [
            'probe' => 'unsupported',
            'features' => ['paginate.lazy'],
            'reason' => 'Page size is fixed at 175 (~400 KB per page) and the limit param is not accepted, so a lazy() walk would pull multi-hundred-KB pages just to prove it; no attempt here to avoid that cost. paginate.total is not blocked by this — see "paginate by total_cards" above.',
        ],
        'filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'All filtering lives in the q DSL (set:khm type:god); a plain where() renders rarity=mythic as a bare top-level param, which Scryfall ignores outright (curl-verified: still 323 cards, mixed rarities returned) rather than filtering.',
            'attempt' => ['probe' => 'filter', 'model' => CardSearchResult::class, 'query' => fn (Builder $query) => $query->withQuery(['q' => 'set:khm']), 'field' => 'rarity', 'value' => 'mythic'],
        ],
    ],
];
