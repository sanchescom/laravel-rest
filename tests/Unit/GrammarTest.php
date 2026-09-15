<?php

declare(strict_types=1);

use Sanchescom\Rest\Query\JsonApiGrammar;
use Sanchescom\Rest\Query\PlainGrammar;
use Sanchescom\Rest\Query\QueryState;

function state(callable $mutator): QueryState
{
    $state = new QueryState;
    $mutator($state);

    return $state;
}

it('compiles empty state to empty array', function () {
    expect((new PlainGrammar)->compile(new QueryState))->toBe([])
        ->and((new QueryState)->isEmpty())->toBeTrue();
});

it('compiles plain filters, sort and paging', function () {
    $state = state(function (QueryState $s) {
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

    expect((new PlainGrammar)->compile($state))->toBe([
        'include' => 'author',
        'userId' => 1,
        'age[gte]' => 30,
        'sort' => '-date,name',
        'limit' => 10,
        'offset' => 20,
        'page' => 2,
    ]);
});

it('compiles json:api filters, sort and paging', function () {
    $state = state(function (QueryState $s) {
        $s->wheres = [
            ['field' => 'userId', 'operator' => '=', 'value' => 1],
            ['field' => 'age', 'operator' => 'gte', 'value' => 30],
        ];
        $s->orders = [['field' => 'date', 'direction' => 'desc']];
        $s->limit = 10;
        $s->page = 2;
    });

    expect((new JsonApiGrammar)->compile($state))->toBe([
        'filter' => ['userId' => 1, 'age' => ['gte' => 30]],
        'sort' => '-date',
        'page' => ['size' => 10, 'number' => 2],
    ]);
});

it('compiles in filters as comma lists', function () {
    $state = state(function (QueryState $s) {
        $s->wheres = [['field' => 'postId', 'operator' => 'in', 'value' => [1, 2, 3]]];
    });

    expect((new PlainGrammar)->compile($state))->toBe(['postId' => '1,2,3'])
        ->and((new JsonApiGrammar)->compile($state))->toBe(['filter' => ['postId' => '1,2,3']]);
});

it('passes non-array in values through', function () {
    $state = state(function (QueryState $s) {
        $s->wheres = [['field' => 'postId', 'operator' => 'in', 'value' => '1,2']];
    });

    expect((new PlainGrammar)->compile($state))->toBe(['postId' => '1,2']);
});
