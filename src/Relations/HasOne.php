<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Closure;
use InvalidArgumentException;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Model;

class HasOne extends Relation
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

    public function getResults(): ?Model
    {
        return $this->toHasMany()->getResults()->first();
    }

    public function eagerLoad(Collection $parents, string $name, ?Closure $constraint = null): Collection
    {
        $firsts = [];

        $this->toHasMany()->loadGroups(
            $parents,
            $constraint,
            function (Model $parent, Collection $group) use ($name, &$firsts) {
                $first = $group->first();
                $parent->setRelation($name, $first);

                if ($first !== null) {
                    $firsts[spl_object_id($first)] = $first;
                }
            },
        );

        return $this->relatedInstance()->newCollection(array_values($firsts));
    }

    private function toHasMany(): HasMany
    {
        $hasMany = new HasMany($this->parent, $this->related, $this->foreignKey);

        if ($this->nested) {
            $hasMany->nested();
        }

        if ($this->batch) {
            $hasMany->batch();
        }

        return $hasMany;
    }
}
