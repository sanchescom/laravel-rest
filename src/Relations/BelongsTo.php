<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Illuminate\Support\Str;
use Sanchescom\Rest\Model;

class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        $foreignKey = $this->foreignKey
            ?? Str::camel(class_basename($this->related)).'Id';

        $key = $this->parent->getAttribute($foreignKey);

        if ($key === null) {
            return null;
        }

        $result = $this->relatedInstance()->newBuilder()->get($key);

        return $result instanceof Model ? $result : null;
    }
}
