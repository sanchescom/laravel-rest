<?php

declare(strict_types=1);

use Sanchescom\Rest\Tests\Live\Support\LiveCatalog;

function liveCatalogDir(array $files): string
{
    $dir = sys_get_temp_dir().'/laravel-rest-catalog-'.uniqid();
    mkdir($dir);

    foreach ($files as $name => $content) {
        file_put_contents("{$dir}/{$name}.php", $content);
    }

    return $dir;
}

afterEach(function () {
    // Clean up temp directories created by liveCatalogDir()
    foreach (glob(sys_get_temp_dir().'/laravel-rest-catalog-*', GLOB_ONLYDIR) as $dir) {
        foreach (glob("{$dir}/*.php") as $file) {
            unlink($file);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
});

it('builds a dataset of every api scenario sorted by slug', function () {
    $dir = liveCatalogDir([
        'zeta' => "<?php return ['name' => 'Zeta', 'base_uri' => 'https://z/', 'scenarios' => ['list' => ['probe' => 'list']]];",
        'alpha' => "<?php return ['name' => 'Alpha', 'base_uri' => 'https://a/', 'scenarios' => ['find' => ['probe' => 'find'], 'list' => ['probe' => 'list']]];",
    ]);

    expect(LiveCatalog::scenarios($dir))->toBe([
        'alpha › find' => ['alpha', 'find'],
        'alpha › list' => ['alpha', 'list'],
        'zeta › list' => ['zeta', 'list'],
    ])->and(LiveCatalog::api('zeta', $dir)['name'])->toBe('Zeta');
});

it('rejects catalog files without the required keys', function () {
    LiveCatalog::apis(liveCatalogDir(['broken' => "<?php return ['name' => 'Broken'];"]));
})->throws(InvalidArgumentException::class, 'must return name, base_uri and scenarios');

it('rejects unknown apis', function () {
    LiveCatalog::api('missing', liveCatalogDir([]));
})->throws(InvalidArgumentException::class, 'Unknown live API [missing].');

it('limits the dataset to LIVE_ONLY slugs unless unfiltered', function () {
    $dir = liveCatalogDir([
        'alpha' => "<?php return ['name' => 'Alpha', 'base_uri' => 'https://a/', 'scenarios' => ['list' => ['probe' => 'list']]];",
        'beta' => "<?php return ['name' => 'Beta', 'base_uri' => 'https://b/', 'scenarios' => ['list' => ['probe' => 'list']]];",
    ]);

    putenv('LIVE_ONLY=beta, gamma');

    try {
        expect(array_keys(LiveCatalog::scenarios($dir)))->toBe(['beta › list'])
            ->and(array_keys(LiveCatalog::scenarios($dir, filtered: false)))->toBe(['alpha › list', 'beta › list']);
    } finally {
        putenv('LIVE_ONLY');
    }
});
