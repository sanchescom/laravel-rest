<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

interface Grammar
{
    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array;
}
