<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;

class UserModel extends Model
{
    protected ?string $dataKey = 'data';
}

class PlainItem extends Model
{
    protected ?string $endpoint = 'custom/items';

    protected ?string $dataKey = null;
}

function fakeResolver(ClientInterface $client): void
{
    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    Model::setClientResolver($resolver);
}

function psr(string $json, int $status = 200): Response
{
    return new Response($status, [], $json);
}

it('infers endpoint from class name', function () {
    expect((new UserModel)->getEndpoint())->toBe('user_models')
        ->and((new PlainItem)->getEndpoint())->toBe('custom/items');
});

it('gets a collection and unwraps dataKey', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('user_models', [])->once()
        ->andReturn(psr('{"data":[{"id":1},{"id":2}]}'));
    fakeResolver($client);

    $users = UserModel::get();

    expect($users)->toBeInstanceOf(Collection::class)->toHaveCount(2)
        ->and($users->first())->toBeInstanceOf(UserModel::class)
        ->and($users->first()->id)->toBe(1);
});

it('gets a single model by id', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('user_models/1', [])->once()
        ->andReturn(psr('{"data":{"id":1,"name":"Tim"}}'));
    fakeResolver($client);

    $user = UserModel::get(1);

    expect($user)->toBeInstanceOf(UserModel::class)->and($user->name)->toBe('Tim');
});

it('works without a dataKey', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('custom/items', [])->once()
        ->andReturn(psr('[{"id":1}]'));
    fakeResolver($client);

    expect(PlainItem::get())->toHaveCount(1);
});

it('returns empty collection when dataKey missing in response', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->once()->andReturn(psr('{"unexpected":true}'));
    fakeResolver($client);

    expect(UserModel::get())->toBeInstanceOf(Collection::class)->toHaveCount(0);
});

it('getMany hydrates in input order', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('getMany')->with(['user_models/1', 'user_models/2'])->once()
        ->andReturn([psr('{"data":{"id":1}}'), psr('{"data":{"id":2}}')]);
    fakeResolver($client);

    $users = UserModel::getMany([1, null, 2, '']);

    expect($users->pluck('id')->all())->toBe([1, 2]);
});

it('posts attributes and hydrates the response', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('post')->with('user_models', ['name' => 'Tim'])->once()
        ->andReturn(psr('{"data":{"id":5,"name":"Tim"}}'));
    fakeResolver($client);

    expect(UserModel::post(['name' => 'Tim'])->id)->toBe(5);
});

it('puts by explicit id', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('put')->with('user_models/2', ['email' => 'a@b.c'])->once()
        ->andReturn(psr('{"data":{"id":2,"email":"a@b.c"}}'));
    fakeResolver($client);

    expect(UserModel::put(2, ['email' => 'a@b.c'])->email)->toBe('a@b.c');
});

it('puts an instance using its own key', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('put')->with('user_models/2', ['id' => 2, 'email' => 'new@b.c'])->once()
        ->andReturn(psr('{"data":{"id":2}}'));
    fakeResolver($client);

    $user = new UserModel(['id' => 2]);
    $user->email = 'new@b.c';
    $user->put();
});

it('refuses to put without id or key', function () {
    fakeResolver(Mockery::mock(ClientInterface::class));
    UserModel::put();
})->throws(RestException::class);

it('deletes by id and by instance key', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('delete')->with('user_models/1')->twice()->andReturn(psr('', 204));
    fakeResolver($client);

    expect(UserModel::delete(1))->toBeTrue()
        ->and((new UserModel(['id' => 1]))->delete())->toBeTrue();
});

it('refuses to delete without id or key', function () {
    fakeResolver(Mockery::mock(ClientInterface::class));
    UserModel::delete();
})->throws(RestException::class);
