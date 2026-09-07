<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Rest;

class EnvelopedPost extends Model
{
    protected ?string $dataKey = 'data';

    protected ?string $requestDataKey = 'data';
}

class BarePost extends Model
{
    protected ?string $dataKey = null;
}

afterEach(fn () => Rest::restore());

it('wraps post bodies in the request data key', function () {
    Rest::fake(['enveloped_posts' => Rest::response(['data' => ['id' => 1]])]);

    EnvelopedPost::post(['title' => 'x']);

    Rest::assertSent(fn ($request) => $request->data() === ['data' => ['title' => 'x']]);
});

it('wraps put bodies in the request data key', function () {
    Rest::fake(['enveloped_posts/*' => Rest::response(['data' => ['id' => 1]])]);

    EnvelopedPost::put(1, ['title' => 'y']);

    Rest::assertSent(fn ($request) => $request->data() === ['data' => ['title' => 'y']]);
});

it('sends bare bodies when no request data key is set', function () {
    Rest::fake(['bare_posts' => Rest::response(['id' => 1])]);

    BarePost::post(['title' => 'x']);

    Rest::assertSent(fn ($request) => $request->data() === ['title' => 'x']);
});
