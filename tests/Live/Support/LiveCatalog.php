<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use InvalidArgumentException;

final class LiveCatalog
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $loaded = [];

    public static function directory(): string
    {
        return dirname(__DIR__).'/Catalog';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function apis(?string $directory = null): array
    {
        $directory ??= self::directory();

        if (! isset(self::$loaded[$directory])) {
            $apis = [];

            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $api = require $file;

                if (! is_array($api) || ! isset($api['name'], $api['base_uri'], $api['scenarios'])) {
                    throw new InvalidArgumentException("Live catalog file [{$file}] must return name, base_uri and scenarios.");
                }

                $apis[basename($file, '.php')] = $api;
            }

            ksort($apis);

            self::$loaded[$directory] = $apis;
        }

        return self::$loaded[$directory];
    }

    /**
     * @return array<string, mixed>
     */
    public static function api(string $slug, ?string $directory = null): array
    {
        return self::apis($directory)[$slug] ?? throw new InvalidArgumentException("Unknown live API [{$slug}].");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function scenarios(?string $directory = null, bool $filtered = true): array
    {
        $only = $filtered
            ? array_values(array_filter(array_map('trim', explode(',', (string) getenv('LIVE_ONLY')))))
            : [];

        $cases = [];

        foreach (self::apis($directory) as $slug => $api) {
            if ($only !== [] && ! in_array($slug, $only, true)) {
                continue;
            }

            foreach (array_keys($api['scenarios']) as $name) {
                $cases["{$slug} › {$name}"] = [$slug, (string) $name];
            }
        }

        return $cases;
    }
}
