<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class EachPost extends Model
{
    protected ?string $endpoint = 'each_posts';

    protected ?string $dataKey = null;
}

afterEach(fn () => Rest::restore());

it('fetches several builder queries concurrently in order', function () {
    Rest::fake([
        'each_posts' => Rest::response([['id' => 1]]),
        'each_posts/9' => Rest::response(['id' => 9]),
    ]);

    $list = EachPost::where('status', 'active');
    $single = (new EachPost)->newBuilder()->from('each_posts/9');

    $payloads = $list->getEach([$list, $single]);

    expect($list->hydrateMany($payloads[0])->first()->id)->toBe(1)
        ->and($single->hydrateOne($payloads[1])->id)->toBe(9);
    Rest::assertSentCount(2);
    Rest::assertSent(fn ($request) => $request->uri() === 'each_posts' && $request->query() === ['status' => 'active']);
    Rest::assertSent(fn ($request) => $request->uri() === 'each_posts/9' && $request->query() === []);
});

it('returns nothing for no builders', function () {
    Rest::fake([]);

    expect((new EachPost)->newBuilder()->getEach([]))->toBe([]);
    Rest::assertSentCount(0);
});
