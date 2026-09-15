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
        $api = LiveCatalog::api($slug);
        $definition = $api['scenarios'][$scenario];
        $context = new LiveContext($slug, $api, $definition['client'] ?? []);
        $probe = Probes::for((string) $definition['probe']);
        $features = array_values(array_unique([...$probe->features($definition), ...($definition['features'] ?? [])]));

        try {
            $probe->run($context, $definition);

            LiveResults::record($slug, $scenario, $features, LiveResults::PASS, '', $context->requests());
        } catch (LiveUnsupported $unsupported) {
            LiveResults::record($slug, $scenario, $features, LiveResults::UNSUPPORTED, $unsupported->getMessage(), $context->requests());

            Assert::markTestSkipped('unsupported: '.$unsupported->getMessage());
        } catch (Throwable $error) {
            $reason = $error::class.': '.$error->getMessage();

            if (Outage::is($error)) {
                LiveResults::record($slug, $scenario, $features, LiveResults::SKIP, $reason, $context->requests());

                Assert::markTestSkipped('API unavailable: '.$reason);
            }

            LiveResults::record($slug, $scenario, $features, LiveResults::FAIL, $reason, $context->requests());

            throw $error;
        } finally {
            Rest::memoize(false);
            Model::setCacheStore(null);
            Model::unsetClientResolver();
        }
    }
}
