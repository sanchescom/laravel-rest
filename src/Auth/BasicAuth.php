<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

final class BasicAuth implements AuthInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader(
            'Authorization',
            'Basic '.base64_encode("{$this->username}:{$this->password}"),
        );
    }
}
