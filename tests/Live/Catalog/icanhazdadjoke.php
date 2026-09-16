<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Icanhazdadjoke;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class Joke extends Model
{
    protected ?string $endpoint = 'j';

    protected ?string $dataKey = null;

    protected array $headers = ['Accept' => 'application/json'];
}

final class JokeSearch extends Model
{
    protected ?string $endpoint = 'search';

    protected ?string $dataKey = 'results';

    protected array $headers = ['Accept' => 'application/json'];
}

return [
    'name' => 'icanhazdadjoke',
    'docs' => 'https://icanhazdadjoke.com/api',
    'base_uri' => 'https://icanhazdadjoke.com/',
    'traits' => [
        'response' => 'bare-object (detail) / results + total_jokes (search)',
        'pagination' => 'page + limit; total in body:total_jokes',
        'content_negotiation' => 'Accept: application/json required (HTML otherwise)',
        'errors' => '200 with {"status":404}',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'total_jokes'],
    ],
    'scenarios' => [
        'accept header' => [
            'probe' => 'headers',
            'model' => Joke::class,
            'id' => 'R7UfaahVfFd',
            'headers' => ['Accept' => 'application/json'],
            'echo' => false,
        ],
        'find joke' => [
            'probe' => 'find',
            'model' => Joke::class,
            'id' => 'R7UfaahVfFd',
        ],
        'missing joke' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'Unknown id answers HTTP 200 with {"message":..,"status":404} instead of a 404 status, so ModelNotFoundException never fires.',
            'attempt' => ['probe' => 'not-found', 'model' => Joke::class, 'id' => 'doesnotexist123'],
        ],
        'next page' => [
            'probe' => 'unsupported',
            'features' => ['paginate.simple'],
            'reason' => 'next_page is an integer, not a link or has-more flag; the simple-paginate config needs a non-empty string path to a next-page indicator, so only full-page inference is usable.',
        ],
        'search pagination' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.style.page'],
            'reason' => "Search results come back in a non-deterministic order: repeating the identical request returns a different item order (curl-verified across 3 runs), and the same joke id can appear on both page 1 and page 2 of one run. Page boundaries are not stable, so paginate()'s non-overlap guarantee cannot be relied on even though total_jokes itself is correct.",
            'attempt' => ['probe' => 'paginate', 'model' => JokeSearch::class, 'query' => fn (Builder $query) => $query->withQuery(['term' => 'cat']), 'per_page' => 5],
        ],
    ],
];
