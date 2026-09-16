<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use Sanchescom\Rest\Tests\Live\Support\LiveContext;

interface Probe
{
    /**
     * Feature ids (keys of Probes::FEATURES) this scenario verifies.
     *
     * @param  array<string, mixed>  $scenario
     * @return list<string>
     */
    public function features(array $scenario): array;

    /**
     * Run the scenario and assert its semantics with Pest expectations.
     *
     * @param  array<string, mixed>  $scenario
     */
    public function run(LiveContext $context, array $scenario): void;
}
