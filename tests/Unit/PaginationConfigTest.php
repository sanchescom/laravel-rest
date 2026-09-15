<?php

declare(strict_types=1);

use Sanchescom\Rest\Pagination\PaginationConfig;

it('expands presets', function (string $preset, ?string $total, ?string $next) {
    $config = PaginationConfig::fromConfig($preset);

    expect($config->style)->toBe('page')
        ->and($config->total)->toBe($total)
        ->and($config->next)->toBe($next)
        ->and($config->hasMore)->toBeNull();
})->with([
    'laravel' => ['laravel', 'meta.total', 'links.next'],
    'django' => ['django', 'count', 'next'],
    'jsonapi' => ['jsonapi', null, 'links.next'],
]);

it('merges explicit keys over a preset', function () {
    $config = PaginationConfig::fromConfig(['preset' => 'jsonapi', 'total' => 'meta.count', 'style' => 'offset']);

    expect($config->style)->toBe('offset')
        ->and($config->total)->toBe('meta.count')
        ->and($config->next)->toBe('links.next');
});

it('accepts a config without a preset', function () {
    $config = PaginationConfig::fromConfig(['has_more' => 'meta.has_more']);

    expect($config->style)->toBe('page')
        ->and($config->total)->toBeNull()
        ->and($config->next)->toBeNull()
        ->and($config->hasMore)->toBe('meta.has_more');
});

it('rejects an unknown preset', function () {
    PaginationConfig::fromConfig('cursor');
})->throws(InvalidArgumentException::class, 'Unknown pagination preset [cursor].');

it('rejects unknown keys', function () {
    PaginationConfig::fromConfig(['per_page' => 'meta.per_page']);
})->throws(InvalidArgumentException::class, 'Unknown pagination config key [per_page].');

it('rejects an unknown style', function () {
    PaginationConfig::fromConfig(['style' => 'cursor']);
})->throws(InvalidArgumentException::class, 'Unknown pagination style [cursor].');

it('rejects non-string paths', function () {
    PaginationConfig::fromConfig(['total' => 5]);
})->throws(InvalidArgumentException::class, 'Pagination config key [total] must be a non-empty string.');
