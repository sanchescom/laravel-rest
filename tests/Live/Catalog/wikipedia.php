<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Wikipedia;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;

final class AllPages extends Model
{
    protected ?string $endpoint = 'api.php';

    protected ?string $dataKey = 'query.allpages';

    protected string $primaryKey = 'pageid';
}

final class Pages extends Model
{
    protected ?string $endpoint = 'api.php';

    protected ?string $dataKey = 'query.pages';
}

return [
    'name' => 'Wikipedia Action API',
    'docs' => 'https://www.mediawiki.org/wiki/API:Main_page',
    'base_uri' => 'https://en.wikipedia.org/w/',
    'traits' => [
        'response' => 'nested:query.<module> (arrays) or query.pages keyed by pageid',
        'pagination' => 'continuation-token (continue object echoed back)',
        'filters' => 'module params',
        'deviations' => 'single api.php, action=, format=json',
        'errors' => '200 with error / missing markers',
    ],
    'scenarios' => [
        'list all pages' => [
            'probe' => 'list',
            'model' => AllPages::class,
            'query' => fn (Builder $query) => $query->withQuery(['action' => 'query', 'list' => 'allpages', 'aplimit' => 50, 'format' => 'json']),
            'min' => 50,
        ],
        'continuation pagination' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total', 'paginate.simple', 'paginate.lazy'],
            'reason' => 'The next page is reached only by echoing back the "continue" object from the previous response (apcontinue=...&continue=-||); there is no total, no page number and no offset for paginate()/simplePaginate()/lazy() to use.',
            'attempt' => ['probe' => 'paginate', 'model' => AllPages::class, 'query' => fn (Builder $query) => $query->withQuery(['action' => 'query', 'list' => 'allpages', 'format' => 'json'])],
        ],
        'missing page has no error marker' => [
            'probe' => 'unsupported',
            'features' => ['errors.not-found'],
            'reason' => 'A missing title answers HTTP 200 with query.pages["-1"].missing set, not a 404 — read.list happily hydrates one model that lacks every normal page field instead of throwing.',
            'attempt' => ['probe' => 'list', 'model' => Pages::class, 'query' => fn (Builder $query) => $query->withQuery(['action' => 'query', 'titles' => 'ThisPageDoesNotExistXYZ123', 'format' => 'json']), 'min' => 1, 'fields' => ['pageid']],
        ],
        'no resource paths' => [
            'probe' => 'unsupported',
            'features' => ['read.find'],
            'reason' => 'Everything goes through api.php?action=...; find() appends the id as a path segment (api.php/<title>), which MediaWiki 301-redirects to bare api.php dropping the query string, landing on the HTML API help page instead of JSON.',
            'attempt' => ['probe' => 'find', 'model' => AllPages::class, 'id' => 'SomeTitle'],
        ],
    ],
];
