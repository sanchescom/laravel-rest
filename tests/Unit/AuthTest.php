<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use Sanchescom\Rest\Auth\BasicAuth;
use Sanchescom\Rest\Auth\BearerAuth;
use Sanchescom\Rest\Auth\HeaderAuth;
use Sanchescom\Rest\Clients\GuzzleClient;

it('adds a bearer header', function () {
    $request = (new BearerAuth('tok'))->authenticate(new Request('GET', 'x'));
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer tok');
});

it('adds a basic auth header', function () {
    $request = (new BasicAuth('user', 'pass'))->authenticate(new Request('GET', 'x'));
    expect($request->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('user:pass'));
});

it('adds arbitrary headers', function () {
    $request = (new HeaderAuth(['X-Api-Key' => 'k123', 'X-Version' => '2']))
        ->authenticate(new Request('GET', 'x'));
    expect($request->getHeaderLine('X-Api-Key'))->toBe('k123')
        ->and($request->getHeaderLine('X-Version'))->toBe('2');
});

it('rejects auth config with missing keys', function (array $auth) {
    GuzzleClient::fromConfig(['base_uri' => 'https://api.test/', 'auth' => $auth]);
})->with([
    [['driver' => 'bearer']],
    [['driver' => 'basic', 'username' => 'u']],
    [['driver' => 'header']],
])->throws(InvalidArgumentException::class);
