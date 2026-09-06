<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Auth;

use Psr\Http\Message\RequestInterface;

final class HeaderAuth implements AuthInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(private readonly array $headers) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}
