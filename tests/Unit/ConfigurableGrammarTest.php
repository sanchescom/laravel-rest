<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\ConfigurableGrammar;
use Sanchescom\Rest\Query\JsonApiGrammar;
use Sanchescom\Rest\Query\QueryState;

class ConvPost extends Model
{
    protected ?string $dataKey = null;
}

function convState(callable $mutator): QueryState
{
    $state = new QueryState;
    $mutator($state);

    return $state;
}

function loadedState(): QueryState
{
    return convState(function (QueryState $s) {
        $s->wheres = [
            ['field' => 'userId', 'operator' => '=', 'value' => 1],
            ['field' => 'age', 'operator' => 'gte', 'value' => 30],
        ];
        $s->orders = [
            ['field' => 'date', 'direction' => 'desc'],
            ['field' => 'name', 'direction' => 'asc'],
        ];
        $s->limit = 10;
        $s->offset = 20;
        $s->page = 2;
        $s->extra = ['include' => 'author'];
    });
}

it('defaults to plain grammar output', function () {
    expect(ConfigurableGrammar::fromConfig([])->compile(loadedState()))->toBe([
        'include' => 'author',
        'userId' => 1,
        'age[gte]' => 30,
        'sort' => '-date,name',
        'limit' => 10,
        'offset' => 20,
        'page' => 2,
    ]);
});

it('renames parameters via names', function () {
    $grammar = ConfigurableGrammar::fromConfig([
        'names' => ['limit' => 'per_page', 'offset' => 'skip', 'page' => 'p', 'sort' => 'order_by'],
    ]);

    $query = $grammar->compile(loadedState());

    expect($query['per_page'])->toBe(10)
        ->and($query['skip'])->toBe(20)
        ->and($query['p'])->toBe(2)
        ->and($query['order_by'])->toBe('-date,name')
        ->and($query)->not->toHaveKeys(['limit', 'offset', 'page', 'sort']);
});

it('nests dotted names', function () {
    $grammar = ConfigurableGrammar::fromConfig(['names' => ['limit' => 'page.size']]);

    $query = $grammar->compile(convState(fn (QueryState $s) => $s->limit = 10));

    expect($query)->toBe(['page' => ['size' => 10]]);
});

it('compiles separate sort style', function () {
    $grammar = ConfigurableGrammar::fromConfig([
        'sort' => 'separate',
        'sort_names' => ['field' => 'order_by', 'direction' => 'dir'],
    ]);

    $query = $grammar->compile(loadedState());

    expect($query['order_by'])->toBe('date')->and($query['dir'])->toBe('desc');
});

it('compiles suffix sort style', function () {
    $grammar = ConfigurableGrammar::fromConfig(['sort' => 'suffix']);

    expect($grammar->compile(loadedState())['sort'])->toBe('date:desc,name:asc');
});

it('compiles array sort style', function () {
    $grammar = ConfigurableGrammar::fromConfig(['sort' => 'array']);

    expect($grammar->compile(loadedState())['sort'])->toBe(['date' => 'desc', 'name' => 'asc']);
});

it('compiles django filters', function () {
    $grammar = ConfigurableGrammar::fromConfig(['filters' => 'django']);

    $query = $grammar->compile(loadedState());

    expect($query['userId'])->toBe(1)->and($query['age__gte'])->toBe(30);
});

it('applies casing to filter and sort fields', function () {
    $grammar = ConfigurableGrammar::fromConfig(['casing' => 'snake']);

    $query = $grammar->compile(loadedState());

    expect($query['user_id'])->toBe(1)->and($query['sort'])->toBe('-date,name');
});

it('matches JsonApiGrammar output with the jsonapi preset', function () {
    expect(ConfigurableGrammar::fromConfig('jsonapi')->compile(loadedState()))
        ->toBe((new JsonApiGrammar)->compile(loadedState()));
});

it('applies django preset conventions', function () {
    $query = ConfigurableGrammar::fromConfig('django')->compile(loadedState());

    expect($query['ordering'])->toBe('-date,name')
        ->and($query['page_size'])->toBe(10)
        ->and($query['age__gte'])->toBe(30);
});

it('lets explicit keys override preset keys', function () {
    $grammar = ConfigurableGrammar::fromConfig([
        'preset' => 'django',
        'names' => ['sort' => 'ordering', 'limit' => 'page_size', 'page' => 'p'],
    ]);

    expect($grammar->compile(loadedState())['p'])->toBe(2);
});

it('rejects unknown config keys, styles and presets', function () {
    expect(fn () => ConfigurableGrammar::fromConfig(['nope' => 1]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => ConfigurableGrammar::fromConfig(['sort' => 'zigzag']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => ConfigurableGrammar::fromConfig(['filters' => 'soap']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => ConfigurableGrammar::fromConfig(['casing' => 'kebab']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => ConfigurableGrammar::fromConfig('odata'))
        ->toThrow(InvalidArgumentException::class);
});

it('resolves query config from the standalone resolver', function () {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('get')->with('conv_posts', ['ordering' => '-date'])->once()
        ->andReturn(new Response(200, [], '[]'));

    $resolver = new ClientResolver(['main' => $client]);
    $resolver->setDefaultClient('main');
    $resolver->setQueryConfig('main', 'django');
    Model::setClientResolver($resolver);

    ConvPost::orderBy('date', 'desc')->get();
});

it('compiles in filters per filter style', function (string $filters, array $expected) {
    $state = convState(function (QueryState $s) {
        $s->wheres = [['field' => 'postId', 'operator' => 'in', 'value' => [1, 2]]];
    });

    expect(ConfigurableGrammar::fromConfig(['filters' => $filters])->compile($state))->toBe($expected);
})->with([
    'plain' => ['plain', ['postId' => '1,2']],
    'brackets' => ['brackets', ['filter' => ['postId' => '1,2']]],
    'django' => ['django', ['postId__in' => '1,2']],
]);

it('keeps in values as arrays with the array in style', function () {
    $state = convState(function (QueryState $s) {
        $s->wheres = [['field' => 'post_id', 'operator' => 'in', 'value' => [1, 2]]];
    });

    expect(ConfigurableGrammar::fromConfig(['in' => 'array', 'casing' => 'snake'])->compile($state))
        ->toBe(['post_id' => [1, 2]]);
});

it('rejects an unknown in style', function () {
    ConfigurableGrammar::fromConfig(['in' => 'pipe']);
})->throws(InvalidArgumentException::class, 'Unknown in style [pipe].');
