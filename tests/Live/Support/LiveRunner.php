<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use PHPUnit\Framework\Assert;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\Tests\Live\Probes\Probes;
use Throwable;

final class LiveRunner
{
    public static function run(string $slug, string $scenario): void
    {
        $context = null;
        $definition = null;
        $features = null;

        try {
            $api = LiveCatalog::api($slug);
            $definition = $api['scenarios'][$scenario];
            $context = new LiveContext($slug, $api, $definition['client'] ?? []);
            $probe = Probes::for((string) $definition['probe']);
            $features = array_values(array_unique([...$probe->features($definition), ...($definition['features'] ?? [])]));

            if (in_array($definition['probe'], Probes::PARAMETER_PROBES, true)) {
                $clientConfig = array_replace_recursive($api['client'] ?? [], $definition['client'] ?? []);
                $features = array_values(array_unique([...$features, ...Probes::grammarFeatures($clientConfig, $definition['model'] ?? null)]));
            }

            $probe->run($context, $definition);

            LiveResults::record($slug, $scenario, $features, LiveResults::PASS, '', $context->requests());
        } catch (LiveUnsupported $unsupported) {
            $features ??= self::declaredFeatures($definition);

            LiveResults::record($slug, $scenario, $features, LiveResults::UNSUPPORTED, $unsupported->getMessage(), $context?->requests() ?? 0);

            Assert::markTestSkipped('unsupported: '.$unsupported->getMessage());
        } catch (Throwable $error) {
            // Setup failures (unknown slug/scenario, bad client config, unregistered
            // probe, ...) land here too, so every scenario in the catalog still gets
            // a recorded result instead of silently vanishing from the report.
            $features ??= self::declaredFeatures($definition);
            $reason = $error::class.': '.$error->getMessage();
            $requests = $context?->requests() ?? 0;

            if (Outage::is($error)) {
                LiveResults::record($slug, $scenario, $features, LiveResults::SKIP, $reason, $requests);

                Assert::markTestSkipped('API unavailable: '.$reason);
            }

            LiveResults::record($slug, $scenario, $features, LiveResults::FAIL, $reason, $requests);

            throw $error;
        } finally {
            Rest::memoize(false);
            Model::setCacheStore(null);
            Model::unsetClientResolver();
        }
    }

    /**
     * @param  array<string, mixed>|null  $definition
     * @return list<string>
     */
    private static function declaredFeatures(?array $definition): array
    {
        return array_values((array) ($definition['features'] ?? []));
    }
}
