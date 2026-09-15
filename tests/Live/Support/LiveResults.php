<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

final class LiveResults
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const SKIP = 'skip';

    public const UNSUPPORTED = 'unsupported';

    public static function path(): string
    {
        return getenv('LIVE_RESULTS') ?: dirname(__DIR__, 3).'/build/live/results.jsonl';
    }

    /**
     * @param  list<string>  $features
     */
    public static function record(string $slug, string $scenario, array $features, string $status, string $reason, int $requests): void
    {
        $path = self::path();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $line = json_encode([
            'slug' => $slug,
            'scenario' => $scenario,
            'features' => $features,
            'status' => $status,
            'reason' => $reason,
            'requests' => $requests,
            'at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @return array<string, array{slug: string, scenario: string, features: list<string>, status: string, reason: string, requests: int, at: string}>
     */
    public static function read(?string $path = null): array
    {
        $path ??= self::path();

        if (! is_file($path)) {
            return [];
        }

        $results = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $results[$row['slug'].'|'.$row['scenario']] = $row;
        }

        return $results;
    }
}
