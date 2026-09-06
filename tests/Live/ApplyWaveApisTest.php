<?php

declare(strict_types=1);

use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Support\Json;

it('reads job-api health without auth', function () {
    $base = getenv('JOB_API_BASE_URL');

    $client = GuzzleClient::fromConfig([
        'base_uri' => rtrim((string) $base, '/').'/',
    ]);

    $payload = Json::decode((string) $client->get('health')->getBody());

    expect($payload['status'])->toBe('ok');
})->group('live')->skip(fn () => ! getenv('JOB_API_BASE_URL'), 'JOB_API_BASE_URL not set');

it('reads job-api jobs through X-API-Key header auth', function () {
    $base = getenv('JOB_API_BASE_URL');
    $key = getenv('JOB_API_KEY');

    $client = GuzzleClient::fromConfig([
        'base_uri' => rtrim((string) $base, '/').'/',
        'auth' => ['driver' => 'header', 'headers' => ['X-API-Key' => (string) $key]],
        'retry' => ['times' => 3, 'delay' => 200],
    ]);

    $payload = Json::decode((string) $client->get('api/v1/jobs')->getBody());

    expect($payload)->toHaveKey('jobs')
        ->and($payload['jobs'])->toBeArray();
})->group('live')->skip(fn () => ! getenv('JOB_API_BASE_URL') || ! getenv('JOB_API_KEY'), 'JOB_API_* env not set');

it('lists models from an openai-compatible api through bearer auth', function () {
    $base = getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com';
    $key = getenv('OPENAI_API_KEY');

    $client = GuzzleClient::fromConfig([
        'base_uri' => rtrim((string) $base, '/').'/v1/',
        'auth' => ['driver' => 'bearer', 'token' => (string) $key],
    ]);

    $payload = Json::decode((string) $client->get('models')->getBody());

    expect($payload['data'])->toBeArray()->not->toBeEmpty();
})->group('live')->skip(fn () => ! getenv('OPENAI_API_KEY'), 'OPENAI_API_KEY not set');

it('reads anthropic models through multi-header auth', function () {
    $key = getenv('ANTHROPIC_API_KEY');

    $client = GuzzleClient::fromConfig([
        'base_uri' => 'https://api.anthropic.com/v1/',
        'auth' => ['driver' => 'header', 'headers' => [
            'x-api-key' => (string) $key,
            'anthropic-version' => '2023-06-01',
        ]],
    ]);

    $payload = Json::decode((string) $client->get('models')->getBody());

    expect($payload['data'])->toBeArray()->not->toBeEmpty();
})->group('live')->skip(fn () => ! getenv('ANTHROPIC_API_KEY'), 'ANTHROPIC_API_KEY not set');
