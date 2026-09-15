<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Pagination;

use Illuminate\Pagination\Paginator;

/**
 * Simple paginator whose "has more pages" comes from the API response
 * instead of Laravel's perPage + 1 item heuristic.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Paginator<TKey, TValue>
 */
final class RemotePaginator extends Paginator
{
    /**
     * @param  iterable<TKey, TValue>  $items
     * @param  array<string, mixed>  $options
     */
    public function __construct(mixed $items, int $perPage, bool $hasMore, ?int $currentPage = null, array $options = [])
    {
        parent::__construct($items, $perPage, $currentPage, $options);

        $this->hasMore = $hasMore;
    }
}
