<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Crossref;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Work extends Model
{
    protected ?string $endpoint = 'works';

    protected ?string $dataKey = 'message.items';

    protected string $primaryKey = 'DOI';
}

final class Member extends Model
{
    protected ?string $endpoint = 'members';

    protected ?string $dataKey = 'message';
}

return [
    'name' => 'Crossref REST API',
    'docs' => 'https://api.crossref.org/swagger-ui/index.html',
    'base_uri' => 'https://api.crossref.org/',
    'traits' => [
        'response' => 'double envelope: message.items (list) / message (detail), with a status wrapper',
        'pagination' => 'rows + offset; total in message.total-results',
        'filters' => 'filter=key:value,key:value, a single packed param; unknown top-level params 400',
        'sort' => 'sort=field&order=asc|desc (separate params)',
        'keys' => 'DOI (contains slash) for works, int id for members',
        'errors' => '404 text/plain; 400 JSON validation-failure',
        'rate_limit' => 'polite pool via mailto in User-Agent (harness UA already carries a repo URL)',
    ],
    'client' => [
        'query' => [
            'names' => ['limit' => 'rows'],
            'sort' => 'separate',
            'sort_names' => ['field' => 'sort', 'direction' => 'order'],
        ],
        'pagination' => ['style' => 'offset', 'total' => 'message.total-results'],
    ],
    'scenarios' => [
        'list works' => [
            'probe' => 'list',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->limit(20)->withQuery(['select' => 'DOI,is-referenced-by-count']),
            'min' => 20,
        ],
        'find member' => [
            'probe' => 'find',
            'model' => Member::class,
            'id' => 78,
            'fields' => ['primary-name'],
        ],
        'get many members' => [
            'probe' => 'get-many',
            'model' => Member::class,
            'ids' => [78, 311, 297],
        ],
        'sort by citations' => [
            'probe' => 'sort',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->limit(20)->withQuery(['select' => 'DOI,is-referenced-by-count']),
            'field' => 'is-referenced-by-count',
            'direction' => 'desc',
        ],
        'paginate works' => [
            'probe' => 'paginate',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->withQuery(['select' => 'DOI']),
            'per_page' => 20,
        ],
        'lazy walk works' => [
            'probe' => 'lazy',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->withQuery(['select' => 'DOI']),
            'chunk' => 20,
            'take' => 60,
        ],
        'missing work' => [
            'probe' => 'not-found',
            'model' => Work::class,
            'id' => '10.1000/doesnotexist-live',
        ],
        'invalid rows' => [
            'probe' => 'status',
            'model' => Work::class,
            'query' => fn (Builder $query) => $query->withQuery(['rows' => 'abc']),
            'status' => 400,
        ],
        'filters' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'relation.has-many'],
            'reason' => 'Filters pack field:value pairs into one param (filter=member:78,type:journal-article); a plain where() renders member=78 as a bare top-level param, which the API rejects outright (curl-verified: 400 unknown-parameter) rather than filtering, so it also rules out a fk-style has-many via this endpoint.',
            'attempt' => ['probe' => 'filter', 'model' => Work::class, 'field' => 'member', 'value' => 78],
        ],
    ],
];
