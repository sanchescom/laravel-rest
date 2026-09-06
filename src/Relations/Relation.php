<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Sanchescom\Rest\Model;

abstract class Relation
{
    /**
     * @param  class-string<Model>  $related
     */
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected ?string $foreignKey = null,
    ) {}

    abstract public function getResults(): mixed;

    protected function relatedInstance(): Model
    {
        return new $this->related;
    }
}
