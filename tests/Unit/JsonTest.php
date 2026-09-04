<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Support\Json;

it('decodes json objects and arrays to arrays', function () {
    expect(Json::decode('{"a":1}'))->toBe(['a' => 1])
        ->and(Json::decode('[1,2]'))->toBe([1, 2]);
});

it('throws on malformed json', function () {
    Json::decode('{oops');
})->throws(RestException::class);

it('throws on scalar payloads', function () {
    Json::decode('"just a string"');
})->throws(RestException::class);
