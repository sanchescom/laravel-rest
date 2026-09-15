<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Contracts;

/**
 * Optional companion to ClientResolverInterface; kept separate so custom
 * resolvers do not break. Folds into the resolver context DTO in 2.0.
 */
interface PaginationConfigResolver
{
    /**
     * Pagination config for the client (array, preset string, or null).
     *
     * @return array<string, mixed>|string|null
     */
    public function paginationConfig(?string $name = null): array|string|null;
}
