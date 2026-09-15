<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use Sanchescom\Rest\Tests\Live\Probes\Probes;

final class LiveReport
{
    public const MIN_APIS = 3;

    private const STATUSES = [LiveResults::PASS, LiveResults::FAIL, LiveResults::SKIP, LiveResults::UNSUPPORTED];

    /**
     * @param  array<string, array<string, mixed>>  $apis
     * @param  array<string, array<string, mixed>>  $results
     */
    public static function render(array $apis, array $results, string $version, string $generatedAt): string
    {
        $empty = array_fill_keys(self::STATUSES, []);
        $coverage = array_fill_keys(array_keys(Probes::FEATURES), $empty);
        $perApi = array_fill_keys(array_keys($apis), array_fill_keys(self::STATUSES, 0));
        $totals = array_fill_keys(self::STATUSES, 0);

        foreach ($results as $row) {
            $totals[$row['status']]++;
            $perApi[$row['slug']][$row['status']] = ($perApi[$row['slug']][$row['status']] ?? 0) + 1;

            foreach ($row['features'] as $feature) {
                $coverage[$feature] ??= $empty;
                $coverage[$feature][$row['status']][$row['slug']] = true;
            }
        }

        $name = fn (string $slug) => $apis[$slug]['name'] ?? $slug;
        $names = fn (array $slugs) => implode(', ', array_map($name, array_keys($slugs)));

        $lines = [
            '# Live Verification',
            '',
            "Generated {$generatedAt} from `{$version}` by `composer live:catalog && composer live:report`. Do not edit by hand.",
            '',
            sprintf(
                '%d APIs, %d scenarios: ✅ %d passed · ❌ %d failed · ⏭ %d skipped (API unavailable) · 🚫 %d unsupported.',
                count($apis),
                count($results),
                $totals[LiveResults::PASS],
                $totals[LiveResults::FAIL],
                $totals[LiveResults::SKIP],
                $totals[LiveResults::UNSUPPORTED],
            ),
            '',
            '## Feature coverage',
            '',
            sprintf('A feature counts as confirmed on an API when at least one of its scenarios passed there. Fewer than %d APIs is marked ⚠️.', self::MIN_APIS),
            '',
            '| Feature | Description | Confirmed on | ✅ APIs | ❌ | ⏭ | 🚫 |',
            '| --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($coverage as $feature => $statuses) {
            $confirmed = count($statuses[LiveResults::PASS]);

            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s | %s | %s |',
                $feature,
                Probes::FEATURES[$feature] ?? '',
                $confirmed >= self::MIN_APIS ? (string) $confirmed : "⚠️ {$confirmed}",
                $names($statuses[LiveResults::PASS]),
                $names($statuses[LiveResults::FAIL]),
                $names($statuses[LiveResults::SKIP]),
                $names($statuses[LiveResults::UNSUPPORTED]),
            );
        }

        $lines = [...$lines, '', '## APIs', '', '| API | Base URI | Structure | ✅ | ❌ | ⏭ | 🚫 |', '| --- | --- | --- | --- | --- | --- | --- |'];

        foreach ($apis as $slug => $api) {
            $traits = [];

            foreach ($api['traits'] ?? [] as $axis => $value) {
                $traits[] = str_replace('|', '\|', "{$axis}: {$value}");
            }

            $lines[] = sprintf(
                '| %s | %s | %s | %d | %d | %d | %d |',
                $api['name'],
                $api['base_uri'],
                implode('<br>', $traits),
                $perApi[$slug][LiveResults::PASS],
                $perApi[$slug][LiveResults::FAIL],
                $perApi[$slug][LiveResults::SKIP],
                $perApi[$slug][LiveResults::UNSUPPORTED],
            );
        }

        foreach ([
            LiveResults::FAIL => 'Failures',
            LiveResults::UNSUPPORTED => 'Limitations',
            LiveResults::SKIP => 'Skipped (API unavailable during the run)',
        ] as $status => $title) {
            $lines = [...$lines, '', "## {$title}", ''];
            $rows = array_filter($results, fn (array $row) => $row['status'] === $status);

            if ($rows === []) {
                $lines[] = '_None._';
            }

            foreach ($rows as $row) {
                $lines[] = sprintf('- **%s › %s** — %s', $name($row['slug']), $row['scenario'], $row['reason']);
            }
        }

        return implode("\n", $lines)."\n";
    }
}
