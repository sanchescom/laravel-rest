<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Contracts;

interface ClientResolverInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function client(?string $name = null, array $options = []): ClientInterface;
}
