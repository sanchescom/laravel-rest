<?php

declare(strict_types=1);

use Sanchescom\Rest\Model;

class GuardedStub extends Model
{
    protected array $fillable = ['name', 'age'];

    protected array $casts = ['age' => 'int', 'active' => 'bool'];
}

class OpenStub extends Model {}

it('fills only fillable attributes when fillable is set', function () {
    $m = new GuardedStub(['name' => 'Tim', 'secret' => 'x']);
    expect($m->name)->toBe('Tim')->and($m->secret)->toBeNull();
});

it('fills everything when fillable is empty', function () {
    $m = new OpenStub(['anything' => 'goes']);
    expect($m->anything)->toBe('goes');
});

it('casts attributes on read', function () {
    $m = new GuardedStub(['age' => '30']);
    expect($m->age)->toBe(30);
});

it('leaves null uncast', function () {
    expect((new GuardedStub)->age)->toBeNull();
});

it('supports magic set, isset, unset', function () {
    $m = new OpenStub;
    $m->name = 'Bob';
    expect(isset($m->name))->toBeTrue();
    unset($m->name);
    expect(isset($m->name))->toBeFalse();
});

it('supports array access and json serialization', function () {
    $m = new OpenStub(['id' => 1]);
    expect($m['id'])->toBe(1)
        ->and(json_encode($m))->toBe('{"id":1}')
        ->and($m->toArray())->toBe(['id' => 1]);
});

it('returns primary key via getKey', function () {
    expect((new OpenStub(['id' => 7]))->getKey())->toBe(7)
        ->and((new OpenStub)->getKey())->toBeNull();
});

it('applies casts in toArray', function () {
    expect((new GuardedStub(['age' => '30']))->toArray())->toBe(['age' => 30]);
});
