<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveReport;

function liveReportRow(string $slug, string $scenario, array $features, string $status, string $reason = ''): array
{
    return compact('slug', 'scenario', 'features', 'status', 'reason') + ['requests' => 1, 'at' => '2026-09-15T00:00:00+00:00'];
}

it('renders coverage, apis, failures and limitations', function () {
    $apis = [
        'alpha' => ['name' => 'Alpha', 'base_uri' => 'https://alpha.test/', 'traits' => ['pagination' => 'cursor | next'], 'scenarios' => []],
        'beta' => ['name' => 'Beta', 'base_uri' => 'https://beta.test/', 'traits' => [], 'scenarios' => []],
        'gamma' => ['name' => 'Gamma', 'base_uri' => 'https://gamma.test/', 'traits' => [], 'scenarios' => []],
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
