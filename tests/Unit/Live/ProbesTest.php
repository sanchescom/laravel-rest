<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Probes\Probe;
use Sanchescom\Rest\Tests\Live\Probes\Probes;

class ProbesTestEnvelopeModel extends Model
{
    protected ?string $dataKey = 'data.items';

    protected ?string $requestDataKey = 'data';
}

it('resolves every registered probe', function (string $name) {
    $probe = Probes::for($name);

    expect($probe)->toBeInstanceOf(Probe::class);
})->with(array_map(fn (string $name) => [$name], Probes::names()));

it('rejects unknown probes', function () {
    Probes::for('teleport');
})->throws(InvalidArgumentException::class, 'Unknown live probe [teleport].');

it('derives features from scenario shape', function (string $probe, array $scenario, array $features) {
    expect(Probes::for($probe)->features($scenario))->toBe($features)
        ->and(array_diff($features, array_keys(Probes::FEATURES)))->toBe([]);
})->with([
    'list with nested data key' => ['list', ['model' => ProbesTestEnvelopeModel::class], ['read.list', 'read.data-key', 'read.data-key.nested']],
    'nested has many' => ['has-many', ['nested' => true], ['relation.nested']],
    'batched eager' => ['eager', ['mode' => 'batch'], ['eager.batch']],
    'enveloped create' => ['write', ['op' => 'create', 'model' => ProbesTestEnvelopeModel::class], ['write.create', 'write.envelope']],
    'validation status' => ['status', ['status' => 422], ['errors.validation']],
    'server status' => ['status', ['status' => 503], ['errors.server']],
    'client status' => ['status', ['status' => 418], ['errors.client']],
    'basic auth' => ['auth', ['client' => ['auth' => ['driver' => 'basic']]], ['auth.basic']],
    'memo' => ['cache', ['kind' => 'memo'], ['cache.memo']],
    'unsupported' => ['unsupported', [], []],
]);
