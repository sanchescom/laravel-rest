<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Sanchescom\Rest\Cache\Memo;
use Sanchescom\Rest\Rest;
use Sanchescom\Rest\RestServiceProvider;

afterEach(fn () => Rest::memoize(false));

it('keeps memoization off by default', function () {
    expect(config('rest.memoize'))->toBeFalse()
        ->and(Memo::enabled())->toBeFalse();
});

it('enables memoization from config', function () {
    config()->set('rest.memoize', true);

    app()->register(RestServiceProvider::class, true);

    expect(Memo::enabled())->toBeTrue();
});

it('flushes memo on request and job lifecycle events', function (string $event) {
    // Isolated dispatcher: the app's shared dispatcher also carries Laravel's own
    // core listener (Illuminate\Log\Context\ContextServiceProvider), which is
    // type-hinted to a real JobProcessing instance and throws an ArgumentCountError
    // when this event is fired by bare class name with no payload. That collision
    // is unrelated to what this test checks (our provider registers a flush
    // listener for these event names), so we re-register against a bare
    // dispatcher that only carries our own listeners.
    app()->instance('events', new Dispatcher(app()));
    app()->register(RestServiceProvider::class, true);

    Memo::put('App\Post', 'key', 200, '{}', []);

    event($event);

    expect(Memo::get('App\Post', 'key'))->toBeNull();
})->with([
    'octane request' => ['Laravel\Octane\Events\RequestReceived'],
    'queue job' => ['Illuminate\Queue\Events\JobProcessing'],
    'octane task' => ['Laravel\Octane\Events\TaskReceived'],
    'octane tick' => ['Laravel\Octane\Events\TickReceived'],
]);

it('starts with an empty memo on every boot', function () {
    config()->set('rest.memoize', true);
    Memo::put('App\Post', 'key', 200, '{}', []);

    app()->register(RestServiceProvider::class, true);

    expect(Memo::get('App\Post', 'key'))->toBeNull()
        ->and(Memo::enabled())->toBeTrue();
});
