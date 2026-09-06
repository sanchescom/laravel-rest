<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

final class BearerAuth implements AuthInterface
{
    public function __construct(private readonly string $token) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', "Bearer {$this->token}");
    }
}
