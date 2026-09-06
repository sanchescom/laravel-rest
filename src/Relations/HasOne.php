<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Sanchescom\Rest\Model;

class HasOne extends Relation
{
    protected bool $nested = false;

    public function nested(): static
    {
        $this->nested = true;

        return $this;
    }

    public function getResults(): ?Model
    {
        $hasMany = new HasMany($this->parent, $this->related, $this->foreignKey);

        if ($this->nested) {
            $hasMany->nested();
        }

        return $hasMany->getResults()->first();
    }
}
