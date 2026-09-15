<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Tests\Live\Support\Outage;

it('treats network failures, server errors and rate limits as outages', function (Throwable $error, bool $outage) {
    expect(Outage::is($error))->toBe($outage);
})->with([
    'connect' => [new ConnectException('down', new Request('GET', 'x')), true],
    'server error' => [new ServerException('x', 503), true],
    'rate limited' => [new RequestException('x', 429), true],
    'not found' => [new ModelNotFoundException('x', 404), false],
    'client error' => [new RequestException('x', 400), false],
    'other' => [new RuntimeException('boom'), false],
]);
