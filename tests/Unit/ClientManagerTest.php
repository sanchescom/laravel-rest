<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Sanchescom\Rest\ClientManager;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Contracts\ClientInterface;

function makeManager(array $clients = [], string $default = 'main'): ClientManager
{
    $config = new Repository(['rest' => ['default' => $default, 'clients' => $clients]]);

    return new ClientManager($config, new ClientFactory);
}

$mainConfig = ['provider' => 'guzzle', 'base_uri' => 'https://api.test/'];

it('resolves the default client from config', function () use ($mainConfig) {
    expect(makeManager(['main' => $mainConfig])->client())->toBeInstanceOf(GuzzleClient::class);
});

it('caches clients per name', function () use ($mainConfig) {
    $manager = makeManager(['main' => $mainConfig]);
    expect($manager->client('main'))->toBe($manager->client('main'));
});

it('does not share cache across different options', function () use ($mainConfig) {
    $manager = makeManager(['main' => $mainConfig]);
    expect($manager->client('main', ['headers' => ['X-A' => '1']]))
        ->not->toBe($manager->client('main'));
});

it('throws for unconfigured client', function () {
    makeManager()->client('missing');
})->throws(InvalidArgumentException::class, 'Client [missing] not configured.');

it('throws for unsupported provider', function () {
    makeManager(['main' => ['provider' => 'soap']])->client('main');
})->throws(InvalidArgumentException::class, 'Unsupported provider [soap]');

it('supports extensions by client name and provider name', function () use ($mainConfig) {
    $fake = Mockery::mock(ClientInterface::class);

    $manager = makeManager(['main' => $mainConfig]);
    $manager->extend('main', fn () => $fake);
    expect($manager->client('main'))->toBe($fake);

    $manager2 = makeManager(['other' => ['provider' => 'custom']], 'other');
    $manager2->extend('custom', fn () => $fake);
    expect($manager2->client('other'))->toBe($fake);
});

it('changes the default client', function () use ($mainConfig) {
    $manager = makeManager(['a' => $mainConfig, 'b' => $mainConfig], 'a');
    $manager->setDefaultClient('b');
    expect($manager->getDefaultClient())->toBe('b');
});

it('standalone resolver stores and resolves clients', function () {
    $fake = Mockery::mock(ClientInterface::class);
    $resolver = new ClientResolver(['main' => $fake]);
    $resolver->setDefaultClient('main');

    expect($resolver->client())->toBe($fake)
        ->and($resolver->hasClient('main'))->toBeTrue()
        ->and($resolver->hasClient('nope'))->toBeFalse();
});
