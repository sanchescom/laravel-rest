<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveResults;

beforeEach(function () {
    $this->resultsPath = sys_get_temp_dir().'/laravel-rest-live-'.uniqid().'/results.jsonl';
    putenv('LIVE_RESULTS='.$this->resultsPath);
});

afterEach(function () {
    putenv('LIVE_RESULTS');
    @unlink($this->resultsPath);
    @rmdir(dirname($this->resultsPath));
});

it('appends results and reads the last one per scenario', function () {
    LiveResults::record('pokeapi', 'list', ['read.list'], LiveResults::FAIL, 'boom', 1);
    LiveResults::record('pokeapi', 'list', ['read.list'], LiveResults::PASS, '', 2);
    LiveResults::record('httpbin', 'retry', ['retry.status'], LiveResults::SKIP, 'down', 0);

    $results = LiveResults::read();

    expect(array_keys($results))->toBe(['pokeapi|list', 'httpbin|retry'])
        ->and($results['pokeapi|list']['status'])->toBe('pass')
        ->and($results['pokeapi|list']['requests'])->toBe(2)
        ->and($results['httpbin|retry']['features'])->toBe(['retry.status']);
});

it('reads nothing when no run happened', function () {
    expect(LiveResults::read())->toBe([]);
});
