<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Exceptions\ValidationException;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Support\Json;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class WireEcho extends Model
{
    protected ?string $endpoint = 'echo';

    protected ?string $dataKey = null;

    protected array $headers = ['X-Tenant-Id' => '42'];

    protected ?string $requestDataKey = 'data';
}

function wireResolver(array $clientConfig = [], array|string|null $queryConfig = null): void
{
    $resolver = new ClientResolver([
        'fixture' => GuzzleClient::fromConfig(array_merge(['base_uri' => FixtureServer::$baseUri], $clientConfig)),
    ]);
    $resolver->setDefaultClient('fixture');

    if ($queryConfig !== null) {
        $resolver->setQueryConfig('fixture', $queryConfig);
    }

    Model::setClientResolver($resolver);
}

it('sends renamed query params over the wire', function () {
    wireResolver([], ['names' => ['limit' => 'per_page'], 'sort' => 'separate',
        'sort_names' => ['field' => 'order_by', 'direction' => 'dir']]);

    $raw = Json::decode(file_get_contents(
        FixtureServer::$baseUri.'echo?status=active&order_by=date&dir=desc&per_page=5'
    ) ?: '{}');

    expect($raw['query'])->toBe(['status' => 'active', 'order_by' => 'date', 'dir' => 'desc', 'per_page' => '5']);
})->group('integration');

it('proves headers on the wire via a raw guzzle client with per-request options', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'options' => ['headers' => ['X-Tenant-Id' => '42', 'Accept-Language' => 'de']],
    ]);

    $payload = Json::decode((string) $client->get('echo')->getBody());

    expect($payload['headers']['X-Tenant-Id'] ?? null)->toBe('42')
        ->and($payload['headers']['Accept-Language'] ?? null)->toBe('de');
})->group('integration');

it('sends patch over the wire when configured', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'update_method' => 'patch',
    ]);

    $payload = Json::decode((string) $client->put('echo', ['a' => 1])->getBody());

    expect($payload['method'])->toBe('PATCH')
        ->and($payload['body'])->toBe(['a' => 1]);
})->group('integration');

it('maps nested error keys over the wire', function () {
    $client = GuzzleClient::fromConfig([
        'base_uri' => FixtureServer::$baseUri,
        'errors_key' => 'error.details',
    ]);

    try {
        $client->post('invalid-nested', ['x' => 1]);
        expect(true)->toBeFalse('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['name' => ['Required.']]);
    }
})->group('integration');
