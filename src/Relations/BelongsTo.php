<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Closure;
use Illuminate\Support\Str;
use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Model;

class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        $key = $this->parent->getAttribute($this->foreignKeyName());

        if ($key === null) {
            return null;
        }

        $result = $this->relatedInstance()->newBuilder()->get($key);

        return $result instanceof Model ? $result : null;
    }

    public function eagerLoad(Collection $parents, string $name, ?Closure $constraint = null): Collection
    {
        $foreignKey = $this->foreignKeyName();
        $related = $this->relatedInstance();

        $keys = $parents
            ->map(fn (Model $parent) => $parent->getAttribute($foreignKey))
            ->filter(fn (mixed $key) => $key !== null && $key !== '')
            ->unique(fn (mixed $key) => (string) $key)
            ->values()
            ->all();

        $loaded = match (true) {
            $keys === [] => $related->newCollection(),
            $this->batch => $this->loadBatched($related, $keys, $constraint),
            default => $this->loadEach($related, $keys, $constraint),
        };

        $byKey = [];

        foreach ($loaded as $model) {
            $byKey[(string) $model->getKey()] = $model;
        }

        foreach ($parents as $parent) {
            $key = $parent->getAttribute($foreignKey);

            $parent->setRelation($name, $key === null ? null : ($byKey[(string) $key] ?? null));
        }

        return $loaded;
    }

    private function foreignKeyName(): string
    {
        return $this->foreignKey ?? Str::camel(class_basename($this->related)).'Id';
    }

    /**
     * @param  list<mixed>  $keys
     * @return Collection<int, Model>
     */
    private function loadBatched(Model $related, array $keys, ?Closure $constraint): Collection
    {
        $builder = $related->newBuilder()->whereIn($related->getKeyName(), $keys);

        if ($constraint !== null) {
            $constraint($builder);
        }

        $result = $builder->get();

        return $result instanceof Collection ? $result : $related->newCollection([$result]);
    }

    /**
     * @param  list<mixed>  $keys
     * @return Collection<int, Model>
     */
    private function loadEach(Model $related, array $keys, ?Closure $constraint): Collection
    {
        $builders = array_map(function (mixed $key) use ($related, $constraint) {
            $builder = $related->newBuilder()->from($related->getEndpoint().'/'.$key);

            if ($constraint !== null) {
                $constraint($builder);
            }

            return $builder;
        }, $keys);

        return $related->newCollection(array_map(
            fn (Builder $builder, array $payload) => $builder->hydrateOne($payload),
            $builders,
            $builders[0]->getEach($builders),
        ));
    }
}
