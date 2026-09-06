<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

interface AuthInterface
{
    public function authenticate(RequestInterface $request): RequestInterface;
}
