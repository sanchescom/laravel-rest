<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveReport;

function liveReportRow(string $slug, string $scenario, array $features, string $status, string $reason = ''): array
{
    return compact('slug', 'scenario', 'features', 'status', 'reason') + ['requests' => 1, 'at' => '2026-09-15T00:00:00+00:00'];
}

it('renders coverage, apis, failures and limitations', function () {
    $apis = [
        'alpha' => ['name' => 'Alpha', 'base_uri' => 'https://alpha.test/', 'traits' => ['pagination' => 'cursor | next'], 'scenarios' => ['list' => [], 'sort' => []]],
        'beta' => ['name' => 'Beta', 'base_uri' => 'https://beta.test/', 'traits' => [], 'scenarios' => ['list' => [], 'filter' => []]],
        'gamma' => ['name' => 'Gamma', 'base_uri' => 'https://gamma.test/', 'traits' => [], 'scenarios' => ['list' => [], 'retry' => []]],
    ];

    $results = [
        'alpha|list' => liveReportRow('alpha', 'list', ['read.list'], 'pass'),
        'beta|list' => liveReportRow('beta', 'list', ['read.list'], 'pass'),
        'gamma|list' => liveReportRow('gamma', 'list', ['read.list'], 'pass'),
        'alpha|sort' => liveReportRow('alpha', 'sort', ['query.sort'], 'fail', 'Expected desc order'),
        'beta|filter' => liveReportRow('beta', 'filter', ['query.filter'], 'unsupported', 'No filters on list endpoints'),
        'gamma|retry' => liveReportRow('gamma', 'retry', ['retry.status'], 'skip', 'ConnectException: down'),
    ];

    $markdown = LiveReport::render($apis, $results, '1.6.0-3-gabc', '2026-09-15 10:00 UTC');

    expect($markdown)
        ->toContain('Generated 2026-09-15 10:00 UTC from `1.6.0-3-gabc`')
        ->toContain('| `read.list` | List a collection | 3 | Alpha, Beta, Gamma |')
        ->toContain('| `query.sort` | Sort on the server (orderBy) | ⚠️ 0 |')
        ->toContain('| `write.create` | Create (POST) | ⚠️ 0 |')
        ->toContain('- **Alpha › sort** — Expected desc order')
        ->toContain('- **Beta › filter** — No filters on list endpoints')
        ->toContain('- **Gamma › retry** — ConnectException: down')
        ->toContain('pagination: cursor \| next');
});

it('keeps multi-line and long reasons on one list line', function () {
    $apis = [
        'alpha' => ['name' => 'Alpha', 'base_uri' => 'https://alpha.test/', 'traits' => [], 'scenarios' => ['sort' => []]],
        'beta' => ['name' => 'Beta', 'base_uri' => 'https://beta.test/', 'traits' => [], 'scenarios' => ['retry' => []]],
    ];

    $results = [
        'alpha|sort' => liveReportRow('alpha', 'sort', ['query.sort'], 'fail', "Failed asserting that two arrays are identical.\n--- Expected\n+++ Actual\n-    0 => 'a'"),
        'beta|retry' => liveReportRow('beta', 'retry', ['retry.status'], 'skip', str_repeat('x', 400)),
    ];

    $markdown = LiveReport::render($apis, $results, '1.0.0', '2026-09-15 10:00 UTC');

    expect($markdown)
        ->toContain('- **Alpha › sort** — Failed asserting that two arrays are identical. --- Expected +++ Actual - 0 => \'a\'')
        ->not->toContain('-    0 =>');

    // Verify Beta › retry line has exactly 300 x's followed by …
    $lines = explode("\n", $markdown);
    $retryLines = array_filter($lines, fn ($line) => str_starts_with($line, '- **Beta › retry**'));
    expect($retryLines)->toHaveCount(1);

    $retryLine = reset($retryLines);
    $reasonStart = mb_strpos($retryLine, '— ', 0, 'UTF-8');
    expect($reasonStart)->not->toBe(false);
    $reasonPart = mb_substr($retryLine, $reasonStart + 2, null, 'UTF-8');
    expect($reasonPart)->toBe(str_repeat('x', 300).'…');
});

it('lists scenarios declared in the catalog but missing from results as not run', function () {
    $apis = [
        'alpha' => [
            'name' => 'Alpha',
            'base_uri' => 'https://alpha.test/',
            'traits' => [],
            'scenarios' => ['list' => ['probe' => 'list'], 'sort' => ['probe' => 'sort']],
        ],
        'beta' => [
            'name' => 'Beta',
            'base_uri' => 'https://beta.test/',
            'traits' => [],
            'scenarios' => ['list' => ['probe' => 'list']],
        ],
    ];

    $results = [
        'alpha|list' => liveReportRow('alpha', 'list', ['read.list'], 'pass'),
        'beta|list' => liveReportRow('beta', 'list', ['read.list'], 'pass'),
    ];

    $markdown = LiveReport::render($apis, $results, '1.0.0', '2026-09-15 10:00 UTC');

    expect($markdown)
        ->toContain('1 not run')
        ->toContain('## Not run')
        ->toContain('- **Alpha › sort** — no result recorded');
});

it('reports none not run when every declared scenario has a result', function () {
    $apis = [
        'alpha' => ['name' => 'Alpha', 'base_uri' => 'https://alpha.test/', 'traits' => [], 'scenarios' => ['list' => ['probe' => 'list']]],
    ];

    $results = ['alpha|list' => liveReportRow('alpha', 'list', ['read.list'], 'pass')];

    $markdown = LiveReport::render($apis, $results, '1.0.0', '2026-09-15 10:00 UTC');

    expect($markdown)->toContain('0 not run');

    $lines = explode("\n", $markdown);
    $section = array_slice($lines, array_search('## Not run', $lines) + 1);

    expect(trim($section[1] ?? ''))->toBe('_None._');
});

it('ignores result rows whose scenario no longer exists in the catalog', function () {
    $apis = [
        'alpha' => [
            'name' => 'Alpha',
            'base_uri' => 'https://alpha.test/',
            'traits' => [],
            'scenarios' => ['list' => ['probe' => 'list']],
        ],
    ];

    $results = [
        'alpha|list' => liveReportRow('alpha', 'list', ['read.list'], 'pass'),
        'alpha|removed scenario' => liveReportRow('alpha', 'removed scenario', ['query.sort'], 'fail', 'stale failure'),
    ];

    $markdown = LiveReport::render($apis, $results, '1.0.0', '2026-09-15 10:00 UTC');

    expect($markdown)
        ->toContain('1 APIs, 1 scenarios: ✅ 1 passed · ❌ 0 failed')
        ->toContain('1 stale result rows ignored (scenario no longer in the catalog).')
        ->not->toContain('removed scenario')
        ->not->toContain('stale failure')
        ->toContain('| `query.sort` | Sort on the server (orderBy) | ⚠️ 0 |');

    $lines = explode("\n", $markdown);
    $section = array_slice($lines, array_search('## Failures', $lines) + 1);

    expect(trim($section[1] ?? ''))->toBe('_None._');
});

it('omits the stale line when there are no stale rows', function () {
    $apis = ['alpha' => ['name' => 'Alpha', 'base_uri' => 'https://alpha.test/', 'traits' => [], 'scenarios' => ['list' => ['probe' => 'list']]]];
    $results = ['alpha|list' => liveReportRow('alpha', 'list', ['read.list'], 'pass')];

    $markdown = LiveReport::render($apis, $results, '1.0.0', '2026-09-15 10:00 UTC');

    expect($markdown)->not->toContain('stale result rows ignored');
});
