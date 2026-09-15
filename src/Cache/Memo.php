<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Cache;

/**
 * Successful GET responses remembered for one application request.
 * Reset by the service provider at Octane request start and before each queue job.
 */
final class Memo
{
    private static bool $enabled = false;

    /** @var array<string, array<string, array{status: int, body: string}>> */
    private static array $entries = [];

    public static function enable(bool $enabled = true): void
    {
        self::$enabled = $enabled;

        if (! $enabled) {
            self::flush();
        }
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * @return array{status: int, body: string}|null
     */
    public static function get(string $modelClass, string $key): ?array
    {
        return self::$entries[$modelClass][$key] ?? null;
    }

    public static function put(string $modelClass, string $key, int $status, string $body): void
    {
        self::$entries[$modelClass][$key] = ['status' => $status, 'body' => $body];
    }

    public static function forget(string $modelClass): void
    {
        unset(self::$entries[$modelClass]);
    }

    public static function flush(): void
    {
        self::$entries = [];
    }
}
