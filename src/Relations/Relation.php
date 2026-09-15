<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Closure;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Exceptions\RestException;
use Sanchescom\Rest\Model;

abstract class Relation
{
    protected const NESTED_BATCH_MESSAGE = 'Nested relations cannot be batched.';

    protected bool $batch = false;

    /**
     * @param  class-string<Model>  $related
     */
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected ?string $foreignKey = null,
    ) {}

    abstract public function getResults(): mixed;

    /**
     * Load one request per batch instead of one per parent when eager loading.
     */
    public function batch(): static
    {
        $this->batch = true;

        return $this;
    }

    /**
     * Load this relation for every parent and store it on each of them.
     *
     * @param  Collection<int, Model>  $parents
     * @return Collection<int, Model> every loaded related model
     */
    public function eagerLoad(Collection $parents, string $name, ?Closure $constraint = null): Collection
    {
        throw new RestException(sprintf(
            'Relation [%s] on [%s] does not support eager loading.',
            $name,
            $this->parent::class,
        ));
    }

    protected function relatedInstance(): Model
    {
        return new $this->related;
    }
}
