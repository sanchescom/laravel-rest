<?php

declare(strict_types=1);

use Sanchescom\Rest\Events\ModelCreated;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class EventedPost extends Model
{
    protected ?string $dataKey = null;
}

afterEach(function () {
    EventedPost::flushEventListeners();
    Model::setEventDispatcher(null);
    Rest::restore();
});

it('fires creating and created around post', function () {
    Rest::fake(['evented_posts' => Rest::response(['id' => 1])]);
    $log = [];

    EventedPost::creating(function (EventedPost $model) use (&$log) {
        $log[] = 'creating:'.$model->title;
    });
    EventedPost::created(function (EventedPost $model) use (&$log) {
        $log[] = 'created:'.$model->id;
    });

    EventedPost::post(['title' => 'x']);

    expect($log)->toBe(['creating:x', 'created:1']);
});

it('cancels post when creating returns false', function () {
    Rest::fake(['evented_posts' => Rest::response(['id' => 1])]);
    EventedPost::creating(fn () => false);

    expect(EventedPost::post(['title' => 'x']))->toBeNull();
    Rest::assertSentCount(0);
});

it('fires updating and updated around put, deleting and deleted around delete', function () {
    Rest::fake(['evented_posts/*' => Rest::response(['id' => 5])]);
    $log = [];

    EventedPost::updating(function () use (&$log) {
        $log[] = 'updating';
    });
    EventedPost::updated(function () use (&$log) {
        $log[] = 'updated';
    });
    EventedPost::deleting(function () use (&$log) {
        $log[] = 'deleting';
    });
    EventedPost::deleted(function () use (&$log) {
        $log[] = 'deleted';
    });

    EventedPost::put(5, ['title' => 'y']);
    EventedPost::delete(5);

    expect($log)->toBe(['updating', 'updated', 'deleting', 'deleted']);
});

it('cancels delete when deleting returns false', function () {
    Rest::fake(['evented_posts/*' => Rest::response([])]);
    EventedPost::deleting(fn () => false);

    expect(EventedPost::delete(5))->toBeFalse();
    Rest::assertSentCount(0);
});

it('dispatches bridge events to a dispatcher', function () {
    Rest::fake(['evented_posts' => Rest::response(['id' => 1])]);
    $dispatched = [];

    Model::setEventDispatcher(new class($dispatched)
    {
        /** @var list<string> */
        public array $bag;

        public function __construct(array &$bag)
        {
            $this->bag = &$bag;
        }

        public function dispatch(object $event): void
        {
            $this->bag[] = $event::class;
        }
    });

    EventedPost::post(['title' => 'x']);

    expect($dispatched)->toBe([ModelCreated::class]);
});
