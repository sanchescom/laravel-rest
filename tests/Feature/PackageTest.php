<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientManager;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Model;

class FeatureUser extends Model
{
    protected ?string $dataKey = 'data';

    protected ?string $endpoint = 'users';
}

it('registers the rest manager as a singleton', function () {
    expect(app('rest'))->toBeInstanceOf(ClientManager::class)
        ->and(app('rest'))->toBe(app('rest'))
        ->and(app(ClientResolverInterface::class))->toBe(app('rest'));
});

it('merges the default config', function () {
    expect(config('rest.default'))->toBe('localhost')
        ->and(config('rest.clients.localhost.provider'))->toBe('guzzle');
});

it('resolves models end to end through an extended driver', function () {
    config()->set('rest.default', 'testing');
    config()->set('rest.clients.testing', ['provider' => 'mock']);

    app('rest')->extend('mock', function () {
        $mock = new MockHandler([
            new Response(200, [], '{"data":[{"id":1},{"id":2}]}'),
        ]);

        return new GuzzleClient(new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.test/',
            'http_errors' => false,
        ]));
    });

    $users = FeatureUser::get();

    expect($users)->toHaveCount(2)->and($users->first()->id)->toBe(1);
});
