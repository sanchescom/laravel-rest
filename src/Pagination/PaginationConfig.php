<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Pagination;

use InvalidArgumentException;

final readonly class PaginationConfig
{
    private const KEYS = ['style', 'total', 'next', 'has_more'];

    private const STYLES = ['page', 'offset'];

    private const PRESETS = [
        'laravel' => ['total' => 'meta.total', 'next' => 'links.next'],
        'django' => ['total' => 'count', 'next' => 'next'],
        'jsonapi' => ['next' => 'links.next'],
    ];

    public function __construct(
        public string $style = 'page',
        public ?string $total = null,
        public ?string $next = null,
        public ?string $hasMore = null,
    ) {
        if (! in_array($style, self::STYLES, true)) {
            throw new InvalidArgumentException("Unknown pagination style [{$style}].");
        }
    }

    /**
     * @param  array<string, mixed>|string  $config
     */
    public static function fromConfig(array|string $config): self
    {
        if (is_string($config)) {
            $config = ['preset' => $config];
        }

        if (isset($config['preset'])) {
            $name = (string) $config['preset'];
            $preset = self::PRESETS[$name]
                ?? throw new InvalidArgumentException("Unknown pagination preset [{$name}].");

            unset($config['preset']);

            $config = array_replace($preset, $config);
        }

        foreach (array_keys($config) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new InvalidArgumentException("Unknown pagination config key [{$key}].");
            }
        }

        return new self(
            self::path($config, 'style') ?? 'page',
            self::path($config, 'total'),
            self::path($config, 'next'),
            self::path($config, 'has_more'),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function path(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        if ($value !== null && (! is_string($value) || $value === '')) {
            throw new InvalidArgumentException("Pagination config key [{$key}] must be a non-empty string.");
        }

        return $value;
    }
}
