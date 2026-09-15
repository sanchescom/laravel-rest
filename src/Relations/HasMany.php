<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Model;

class HasMany extends Relation
{
    protected bool $nested = false;

    public function nested(): static
    {
        if ($this->batch) {
            throw new InvalidArgumentException(self::NESTED_BATCH_MESSAGE);
        }

        $this->nested = true;

        return $this;
    }

    public function batch(): static
    {
        if ($this->nested) {
            throw new InvalidArgumentException(self::NESTED_BATCH_MESSAGE);
        }

        return parent::batch();
    }

    public function builder(): Builder
    {
        return $this->builderFor($this->parent);
    }

    /**
     * @return Collection<int, Model>
     */
    public function getResults(): Collection
    {
        $result = $this->builder()->get();

        return $result instanceof Collection ? $result : new Collection([$result]);
    }

    public function eagerLoad(Collection $parents, string $name, ?Closure $constraint = null): Collection
    {
        return $this->loadGroups(
            $parents,
            $constraint,
            fn (Model $parent, Collection $group) => $parent->setRelation($name, $group),
        );
    }

    /**
     * Load related models for every parent and hand each parent its group.
     *
     * @internal shared with HasOne
     *
     * @param  Collection<int, Model>  $parents
     * @param  Closure(Model, Collection<int, Model>): void  $assign
     * @return Collection<int, Model>
     */
    public function loadGroups(Collection $parents, ?Closure $constraint, Closure $assign): Collection
    {
        $related = $this->relatedInstance();

        $keyed = $parents
            ->filter(fn (Model $parent) => $parent->getKey() !== null && $parent->getKey() !== '')
            ->unique(fn (Model $parent) => (string) $parent->getKey())
            ->values();

        $groups = match (true) {
            $keyed->isEmpty() => [],
            $this->batch => $this->fetchBatched($keyed, $related, $constraint),
            default => $this->fetchEach($keyed, $constraint),
        };

        foreach ($parents as $parent) {
            $key = $parent->getKey();

            $assign($parent, $related->newCollection($key === null ? [] : ($groups[(string) $key] ?? [])));
        }

        return $related->newCollection(array_merge([], ...array_values($groups)));
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->builder()->{$method}(...$parameters);
    }

    private function foreignKeyName(): string
    {
        return $this->foreignKey ?? Str::camel(class_basename($this->parent)).'Id';
    }

    private function builderFor(Model $parent): Builder
    {
        $related = $this->relatedInstance();

        if ($this->nested) {
            return $related->newBuilder()->from(
                $parent->getEndpoint().'/'.$parent->getKey().'/'.$related->getEndpoint(),
            );
        }

        return $related->newBuilder()->where($this->foreignKeyName(), $parent->getKey());
    }

    /**
     * @param  Collection<int, Model>  $parents
     * @return array<string, list<Model>>
     */
    private function fetchBatched(Collection $parents, Model $related, ?Closure $constraint): array
    {
        $foreignKey = $this->foreignKeyName();

        $builder = $related->newBuilder()->whereIn(
            $foreignKey,
            $parents->map(fn (Model $parent) => $parent->getKey())->all(),
        );

        if ($constraint !== null) {
            $constraint($builder);
        }

        $result = $builder->get();
        $groups = [];

        foreach ($result instanceof Collection ? $result : [$result] as $model) {
            $groups[(string) $model->getAttribute($foreignKey)][] = $model;
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Model>  $parents
     * @return array<string, list<Model>>
     */
    private function fetchEach(Collection $parents, ?Closure $constraint): array
    {
        $builders = $parents->map(function (Model $parent) use ($constraint) {
            $builder = $this->builderFor($parent);

            if ($constraint !== null) {
                $constraint($builder);
            }

            return $builder;
        })->all();

        $payloads = $builders[0]->getEach($builders);
        $groups = [];

        foreach ($parents as $index => $parent) {
            $groups[(string) $parent->getKey()] = array_values($builders[$index]->hydrateMany($payloads[$index])->all());
        }

        return $groups;
    }
}
