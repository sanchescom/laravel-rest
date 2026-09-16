<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Openf1;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\HasMany;

final class Meeting extends Model
{
    protected ?string $endpoint = 'meetings';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'meeting_key';

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'meeting_key');
    }
}

final class Session extends Model
{
    protected ?string $endpoint = 'sessions';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'session_key';
}

return [
    'name' => 'OpenF1',
    'docs' => 'https://openf1.org/',
    'base_uri' => 'https://api.openf1.org/v1/',
    'throttle_ms' => 2000,
    'traits' => [
        'response' => 'bare-array telemetry with fk filters',
        'pagination' => 'none',
        'filters' => 'field=value; operators are appended to the field name (date_start>=...), not bracketed; repeated params for OR',
        'keys' => 'meeting_key / session_key',
        'relations' => 'fk filters only (sessions?meeting_key=...), no path-based detail endpoints',
        'errors' => 'empty result -> 404 {"detail":"No results found."}',
        'rate_limit' => 'documented 3 req/s, 30 req/min anonymous',
    ],
    'scenarios' => [
        'list meetings' => [
            'probe' => 'list',
            'model' => Meeting::class,
            'query' => fn (Builder $query) => $query->where('year', 2024),
            'min' => 20,
        ],
        'filter sessions by meeting' => [
            'probe' => 'filter',
            'model' => Session::class,
            'field' => 'meeting_key',
            'value' => 1229,
            'min' => 3,
        ],
        'eager meeting sessions' => [
            'probe' => 'eager',
            'model' => Meeting::class,
            'query' => fn (Builder $query) => $query->where('year', 2024)->where('country_code', 'BRN'),
            'relation' => 'sessions',
            'mode' => 'concurrent',
            'foreign_key' => 'meeting_key',
        ],
        'empty result is 404' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'errors.not-found'],
            'reason' => 'A filter with no matches answers HTTP 404 (curl-verified: sessions?meeting_key=1 -> 404 "No results found"), so get() throws ModelNotFoundException instead of returning an empty collection.',
            'attempt' => ['probe' => 'filter', 'model' => Session::class, 'field' => 'meeting_key', 'value' => 1],
        ],
        'membership' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in', 'eager.batch'],
            'reason' => 'OR needs repeated params (meeting_key=1228&meeting_key=1229, curl-verified: 8 sessions); the package\'s whereIn renders one comma-joined param instead (meeting_key=1228,1229), which curl-verified returns 404 "No results found".',
            'attempt' => ['probe' => 'where-in', 'model' => Session::class, 'field' => 'meeting_key', 'values' => [1228, 1229]],
        ],
        'detail and belongs-to' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'relation.belongs-to', 'relation.has-many'],
            'reason' => 'There are no path-based detail endpoints; GET meetings/1229 answers 400 (curl-verified), so a single parent cannot be fetched by key, which read.find, belongsTo and the single-parent hasMany probe all require.',
            'attempt' => ['probe' => 'find', 'model' => Meeting::class, 'id' => 1229],
        ],
        'comparison operators' => [
            'probe' => 'unsupported',
            'features' => ['query.filter'],
            'reason' => 'Operators are appended directly to the field name (date_start>=2024-03-01, curl-verified: 3 sessions); the plain grammar renders the bracketed form date_start[>=]=2024-03-01 instead, which curl-verified returns 404 "No results found". Workaround: withQuery().',
            'attempt' => ['probe' => 'list', 'model' => Session::class, 'query' => fn (Builder $query) => $query->where('meeting_key', 1229)->where('date_start', '>=', '2024-03-01'), 'min' => 1],
        ],
    ],
];
