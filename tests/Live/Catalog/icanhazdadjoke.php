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

final class JokeSearchNextPage extends Model
{
    protected ?string $endpoint = 'search';

    protected ?string $dataKey = 'results';

    protected array $headers = ['Accept' => 'application/json'];

    protected array|string|null $pagination = ['next' => 'next_page'];
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
        'quirks' => 'search result ordering is non-deterministic across pages, so page-based pagination cannot be verified',
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
            'attempt' => ['probe' => 'simple-paginate', 'model' => JokeSearchNextPage::class, 'query' => fn (Builder $query) => $query->withQuery(['term' => 'cat']), 'per_page' => 5],
        ],
    ],
];
