<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

final class QueryState
{
    /** @var list<array{field: string, operator: string, value: mixed}> */
    public array $wheres = [];

    /** @var list<array{field: string, direction: string}> */
    public array $orders = [];

    public ?int $limit = null;

    public ?int $offset = null;

    public ?int $page = null;

    /** @var array<string, mixed> */
    public array $extra = [];

    public function isEmpty(): bool
    {
        return $this->wheres === [] && $this->orders === [] && $this->extra === []
            && $this->limit === null && $this->offset === null && $this->page === null;
    }
}
