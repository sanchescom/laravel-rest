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

        // Concurrent mode: match by the requested key, not the response's own
        // getKey() — a response without an id field (or a foreign key that
        // isn't the related primary key) must still resolve eagerly.
        // Batched mode: the whereIn response can't be aligned by request
        // order, so it's matched by each model's own primary key.
        $byKey = $this->batch ? $this->keyByOwnKey($loaded) : $this->keyByRequestedKey($keys, $loaded);

        foreach ($parents as $parent) {
            $key = $parent->getAttribute($foreignKey);
            $key = $key === '' ? null : $key;

            $parent->setRelation($name, $key === null ? null : ($byKey[(string) $key] ?? null));
        }

        return $loaded;
    }

    /**
     * @param  list<mixed>  $keys
     * @param  Collection<int, Model>  $loaded  aligned with $keys, same order
     * @return array<string, Model>
     */
    private function keyByRequestedKey(array $keys, Collection $loaded): array
    {
        $byKey = [];

        foreach ($loaded as $index => $model) {
            $key = $keys[$index] ?? null;

            if ($key === null || $key === '') {
                continue;
            }

            $byKey[(string) $key] = $model;
        }

        return $byKey;
    }

    /**
     * @param  Collection<int, Model>  $loaded
     * @return array<string, Model>
     */
    private function keyByOwnKey(Collection $loaded): array
    {
        $byKey = [];

        foreach ($loaded as $model) {
            $key = $model->getKey();

            if ($key === null || $key === '') {
                continue;
            }

            $byKey[(string) $key] = $model;
        }

        return $byKey;
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
     * @return Collection<int, Model> aligned with $keys, same order — callers match by request index
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

        $collection = $related->newCollection(array_map(
            fn (Builder $builder, array $payload) => $builder->hydrateOne($payload),
            $builders,
            $builders[0]->getEach($builders),
        ));

        $builders[0]->loadRelations($collection);

        return $collection;
    }
}
