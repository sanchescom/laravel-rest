<?php

declare(strict_types=1);

use Sanchescom\Rest\Exceptions\ModelNotFoundException;
use Sanchescom\Rest\Exceptions\RequestException;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Exceptions\ServerException;
use Sanchescom\Rest\Exceptions\ValidationException;

it('maps status codes to exception subclasses', function (int $status, string $class) {
    expect(RequestException::fromStatus('users/1', $status))->toBeInstanceOf($class);
})->with([
    [404, ModelNotFoundException::class],
    [422, ValidationException::class],
    [500, ServerException::class],
    [503, ServerException::class],
    [400, RequestException::class],
    [403, RequestException::class],
]);

it('exposes uri, status and body', function () {
    $e = RequestException::fromStatus('users/1', 400, ['message' => 'Bad']);
    expect($e->uri)->toBe('users/1')
        ->and($e->status)->toBe(400)
        ->and($e->body)->toBe(['message' => 'Bad'])
        ->and($e->getMessage())->toContain('users/1')
        ->and($e)->toBeInstanceOf(RestException::class);
});

it('exposes validation errors', function () {
    $e = RequestException::fromStatus('users', 422, ['errors' => ['email' => ['Invalid']]]);
    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($e->errors())->toBe(['email' => ['Invalid']]);
});
