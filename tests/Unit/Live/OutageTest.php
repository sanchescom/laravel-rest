<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Request;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Tests\Live\Support\Outage;

it('treats network failures, server errors and rate limits as outages', function (Throwable $error, bool $outage) {
    expect(Outage::is($error))->toBe($outage);
})->with([
    'connect' => [new ConnectException('down', new Request('GET', 'x')), true],
    'transfer (reset)' => [new GuzzleRequestException('reset', new Request('GET', 'x')), true],
    'server error' => [new ServerException('x', 503), true],
    'server 500' => [new ServerException('x', 500), false],
    'bad gateway' => [new ServerException('x', 502), true],
    'cloudflare 522' => [new ServerException('x', 522), true],
    'rate limited' => [new RequestException('x', 429), true],
    'not found' => [new ModelNotFoundException('x', 404), false],
    'client error' => [new RequestException('x', 400), false],
    'other' => [new RuntimeException('boom'), false],
]);

it('treats extra statuses as outages only when declared, and only for the status they name', function () {
    expect(Outage::is(new RequestException('x', 405)))->toBeFalse();
    expect(Outage::is(new RequestException('x', 405), [405]))->toBeTrue();
    expect(Outage::is(new ModelNotFoundException('x', 404), [405]))->toBeFalse();
});
