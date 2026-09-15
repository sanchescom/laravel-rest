<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as BaseCollection;
use Sanchescom\Rest\Relations\EagerLoader;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends BaseCollection<TKey, TValue>
 */
class Collection extends BaseCollection
{
    /**
     * @return LengthAwarePaginator<int, TValue>
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $total = $this->count();

        $results = $this->forPage($page, $perPage)->values();

        return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
            'items' => $results,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ]);
    }

    /**
     * Eager load relations onto the models in this collection.
     *
     * @param  string|array<int|string, string|Closure>  $relations
     */
    public function load(string|array $relations): static
    {
        (new EagerLoader)->load($this, is_string($relations) ? [$relations] : $relations);

        return $this;
    }
}
