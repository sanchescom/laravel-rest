<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Closure;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\Clients\FakeClient;
use Sanchescom\Rest\Clients\RecordedRequest;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

final class Rest
{
    private static ?FakeClient $fake = null;

    private static ?ClientResolverInterface $previous = null;

    private static bool $hadPrevious = false;

    /**
     * @param  array<string, ResponseInterface>  $map
     */
    public static function fake(array $map): FakeClient
    {
        $fake = new FakeClient($map);

        if (self::$fake === null) {
            self::$previous = Model::getClientResolver();
            self::$hadPrevious = self::$previous !== null;
        }

        self::$fake = $fake;

        Model::setClientResolver(new class($fake) implements ClientResolverInterface
        {
            public function __construct(private readonly FakeClient $fake) {}

            /**
             * @param  array<string, mixed>  $options
             */
            public function client(?string $name = null, array $options = []): ClientInterface
            {
                return $this->fake;
            }

            public function grammar(?string $name = null): ?string
            {
                return null;
            }

            public function queryConfig(?string $name = null): array|string|null
            {
                return null;
            }
        });

        return $fake;
    }

    /**
     * @param  array<mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public static function response(array|string $body = [], int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers, is_string($body) ? $body : (json_encode($body) ?: '{}'));
    }

    public static function assertSent(Closure $callback): void
    {
        Assert::assertTrue(
            collect(self::recorded())->contains(fn (RecordedRequest $request) => $callback($request)),
            'Expected request was not sent.',
        );
    }

    public static function assertNotSent(Closure $callback): void
    {
        Assert::assertFalse(
            collect(self::recorded())->contains(fn (RecordedRequest $request) => $callback($request)),
            'Unexpected request was sent.',
        );
    }

    public static function assertSentCount(int $count): void
    {
        Assert::assertCount($count, self::recorded());
    }

    /**
     * @return list<RecordedRequest>
     */
    public static function recorded(): array
    {
        if (self::$fake === null) {
            return [];
        }

        return self::$fake->recorded;
    }

    public static function restore(): void
    {
        if (self::$fake === null) {
            return;
        }

        if (self::$hadPrevious && self::$previous !== null) {
            Model::setClientResolver(self::$previous);
        } else {
            Model::unsetClientResolver();
        }

        self::$fake = null;
        self::$previous = null;
        self::$hadPrevious = false;
    }
}
