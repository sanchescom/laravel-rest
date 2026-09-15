<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\JsonApiGrammar;

class QueryPost extends Model
{
    protected ?string $dataKey = null;
}

class JsonApiPost extends Model
{
    protected ?string $endpoint = 'query_posts';

    protected ?string $dataKey = null;

    protected ?string $grammar = JsonApiGrammar::class;
}

function queryResolver(ClientInterface $client): void
{
    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    Model::setClientResolver($resolver);
}

it('compiles where, sort and paging into the query string', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['userId' => 1, 'age[gte]' => 30, 'sort' => '-date', 'limit' => 5])
        ->once()->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    QueryPost::where('userId', 1)->where('age', 'gte', 30)->orderBy('date', 'desc')->limit(5)->get();
});

it('uses the model grammar', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['filter' => ['userId' => 1]])
        ->once()->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    JsonApiPost::where('userId', 1)->get();
});

it('uses the resolver grammar when model has none', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['filter' => ['userId' => 1]])
        ->once()->andReturn(new Response(200, [], '[]'));

    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    $resolver->setGrammar('main', JsonApiGrammar::class);
    Model::setClientResolver($resolver);

    QueryPost::where('userId', 1)->get();
});

it('supports first and count', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->twice()
        ->andReturn(new Response(200, [], '[{"id":1},{"id":2}]'));
    queryResolver($client);

    expect(QueryPost::first()->id)->toBe(1)
        ->and(QueryPost::count())->toBe(2);
});

it('overrides the endpoint with from', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('custom/path', [])->once()
        ->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    (new QueryPost)->newBuilder()->from('custom/path')->get();
});

it('merges withQuery params', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('query_posts', ['include' => 'author'])->once()
        ->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    QueryPost::withQuery(['include' => 'author'])->get();
});

it('sends whereIn as a deduplicated comma list', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['status' => 'draft,review'])
        ->once()->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    QueryPost::whereIn('status', ['draft', 'review', 'draft'])->get();
});

it('treats where with the in operator as whereIn', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')
        ->with('query_posts', ['id' => '1,2'])
        ->once()->andReturn(new Response(200, [], '[]'));
    queryResolver($client);

    QueryPost::where('id', 'in', [1, 2])->get();
});
