<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Contracts;

interface ClientResolverInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function client(?string $name = null, array $options = []): ClientInterface;

    /**
     * Grammar class configured for the client, if any.
     *
     * @return class-string|null
     */
    public function grammar(?string $name = null): ?string;

    /**
     * Query convention config for the client (array, preset string, or null).
     *
     * @return array<string, mixed>|string|null
     */
    public function queryConfig(?string $name = null): array|string|null;
}
