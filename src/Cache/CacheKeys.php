<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Cache;

final class CacheKeys
{
    /**
     * @param  array<string, mixed>  $query
     */
    public static function entry(?string $client, string $modelClass, int $version, string $uri, array $query, ?string $extra = null): string
    {
        return 'rest:cache:'.md5(implode('|', [
            $client ?? '',
            $modelClass,
            (string) $version,
            $uri,
            serialize($query),
            $extra ?? '',
        ]));
    }

    public static function version(string $modelClass): string
    {
        return 'rest:v:'.md5($modelClass);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function memo(?string $client, string $modelClass, string $uri, array $query, ?string $extra = null): string
    {
        return md5(implode('|', [
            $client ?? '',
            $modelClass,
            $uri,
            serialize($query),
            $extra ?? '',
        ]));
    }
}
