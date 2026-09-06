<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Illuminate\Support\Str;
use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Model;

class HasMany extends Relation
{
    protected bool $nested = false;

    public function nested(): static
    {
        $this->nested = true;

        return $this;
    }

    public function builder(): Builder
    {
        $related = $this->relatedInstance();

        if ($this->nested) {
            return $related->newBuilder()->from(
                $this->parent->getEndpoint().'/'.$this->parent->getKey().'/'.$related->getEndpoint(),
            );
        }

        $foreignKey = $this->foreignKey
            ?? Str::camel(class_basename($this->parent)).'Id';

        return $related->newBuilder()->where($foreignKey, $this->parent->getKey());
    }

    /**
     * @return Collection<int, Model>
     */
    public function getResults(): Collection
    {
        $result = $this->builder()->get();

        return $result instanceof Collection ? $result : new Collection([$result]);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->builder()->{$method}(...$parameters);
    }
}
