<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Probes\Probes;
use Sanchescom\Rest\Tests\Live\Support\LiveCatalog;

/**
 * Every namespace a catalog file declares, in source order (should be exactly one).
 *
 * @return list<string>
 */
function catalogFileNamespaces(string $file): array
{
    $namespaces = [];
    $tokens = token_get_all((string) file_get_contents($file));

    foreach ($tokens as $index => $token) {
        if (! is_array($token) || $token[0] !== T_NAMESPACE) {
            continue;
        }

        $name = '';

        for ($i = $index + 1; $i < count($tokens); $i++) {
            $next = $tokens[$i];

            if ($next === ';' || $next === '{') {
                break;
            }

            if (is_array($next)) {
                $name .= $next[1];
            }
        }

        $namespaces[] = trim($name);
    }

    return $namespaces;
}

it('declares exactly one namespace per catalog file, matching Catalog\\StudlyCase(slug), unique across files', function () {
    $seen = [];

    foreach (glob(LiveCatalog::directory().'/*.php') ?: [] as $file) {
        $slug = basename($file, '.php');
        $namespaces = catalogFileNamespaces($file);

        expect($namespaces)->toHaveCount(1, "Catalog file [{$slug}] must declare exactly one namespace.");

        $expected = 'Sanchescom\\Rest\\Tests\\Live\\Catalog\\'.Str::studly($slug);

        expect($namespaces[0])->toBe($expected);
        expect($seen)->not->toContain($namespaces[0], "Namespace [{$namespaces[0]}] is declared by more than one catalog file.");

        $seen[] = $namespaces[0];
    }
});

it('has a well-formed scenario definition', function (string $slug, string $scenario) {
    $definition = LiveCatalog::api($slug)['scenarios'][$scenario];
    $probeName = (string) ($definition['probe'] ?? '');

    expect(in_array($probeName, Probes::names(), true))->toBeTrue("Unknown probe [{$probeName}] in [{$slug} › {$scenario}].");

    foreach (Probes::REQUIRED[$probeName] ?? [] as $key) {
        expect(array_key_exists($key, $definition))->toBeTrue("Scenario [{$slug} › {$scenario}] is missing required key [{$key}] for probe [{$probeName}].");
    }

    if ($probeName === 'has-many' && ! empty($definition['nested'])) {
        expect(array_key_exists('path_suffix', $definition))->toBeTrue("Nested has-many scenario [{$slug} › {$scenario}] needs [path_suffix].");
    }

    if (isset($definition['model'])) {
        expect(class_exists($definition['model']))->toBeTrue("Model [{$definition['model']}] in [{$slug} › {$scenario}] does not exist.");
        expect($definition['model'] === Model::class || is_subclass_of($definition['model'], Model::class))
            ->toBeTrue("Model [{$definition['model']}] in [{$slug} › {$scenario}] must extend ".Model::class.'.');
    }

    $features = null;

    try {
        $features = Probes::for($probeName)->features($definition);
    } catch (Throwable $error) {
        expect(false)->toBeTrue("features() threw for [{$slug} › {$scenario}]: {$error->getMessage()}");
    }

    foreach ([...($features ?? []), ...($definition['features'] ?? [])] as $feature) {
        expect(array_key_exists($feature, Probes::FEATURES))->toBeTrue("Unknown feature [{$feature}] declared by [{$slug} › {$scenario}].");
    }

    foreach ($definition['features'] ?? [] as $feature) {
        expect(str_starts_with((string) $feature, 'grammar.'))->toBeFalse("Scenario [{$slug} › {$scenario}] declares [{$feature}]: grammar features are derived; do not declare them.");
    }

    if ($probeName === 'unsupported') {
        expect(trim((string) ($definition['reason'] ?? '')))->not->toBe('', "Unsupported scenario [{$slug} › {$scenario}] needs a non-empty [reason].");
        expect($definition['features'] ?? [])->not->toBe([], "Unsupported scenario [{$slug} › {$scenario}] needs at least one feature.");

        if (isset($definition['attempt'])) {
            $attempt = $definition['attempt'];
            $attemptProbe = (string) ($attempt['probe'] ?? '');

            expect(in_array($attemptProbe, Probes::names(), true))->toBeTrue("Unknown probe [{$attemptProbe}] in the [attempt] of [{$slug} › {$scenario}].");
            expect($attemptProbe)->not->toBe('unsupported', "The [attempt] of [{$slug} › {$scenario}] must not itself be an [unsupported] probe.");

            foreach (Probes::REQUIRED[$attemptProbe] ?? [] as $key) {
                expect(array_key_exists($key, $attempt))->toBeTrue("The [attempt] of [{$slug} › {$scenario}] is missing required key [{$key}] for probe [{$attemptProbe}].");
            }

            if (isset($attempt['model'])) {
                expect(class_exists($attempt['model']))->toBeTrue("Model [{$attempt['model']}] in the [attempt] of [{$slug} › {$scenario}] does not exist.");
                expect($attempt['model'] === Model::class || is_subclass_of($attempt['model'], Model::class))
                    ->toBeTrue("Model [{$attempt['model']}] in the [attempt] of [{$slug} › {$scenario}] must extend ".Model::class.'.');
            }
        }
    }
})->with(LiveCatalog::scenarios(filtered: false));
