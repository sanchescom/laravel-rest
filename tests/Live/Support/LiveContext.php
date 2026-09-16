<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Config\Repository;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sanchescom\Rest\ClientManager;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;

/**
 * One scenario's view of an API: a fresh resolver whose Guzzle stack records
 * every real request and spaces requests to the same host.
 */
final class LiveContext
{
    public const USER_AGENT = 'laravel-rest-live-verification (+https://github.com/sanchescom/laravel-rest)';

    /** @var list<array{request: RequestInterface, response: mixed, error: mixed, options: array<string, mixed>}> */
    public array $history = [];

    /** @var array<string, float> */
    private static array $nextSlotAt = [];

    /**
     * Test-only: clear reserved host slots so a fresh test isn't delayed by a
     * previous test's throttling on the same host.
     */
    public static function resetThrottle(): void
    {
        self::$nextSlotAt = [];
    }

    /**
     * @param  array<string, mixed>  $api
     * @param  array<string, mixed>  $override  scenario-level client config merged over the API's client config
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $api,
        array $override = [],
    ) {
        $this->useClient(array_replace_recursive($api['client'] ?? [], $override));
    }

    /**
     * Number of logical requests. Guzzle's history middleware records every
     * redirect hop (e.g. Django APPEND_SLASH answering GET /x with a 301/302
     * to /x/), but a redirect hop is transport, not a distinct logical
     * request — so it's excluded here. An entry with no response (a
     * transport error) still counts. Use history for the full hop list.
     */
    public function requests(): int
    {
        return count(array_filter($this->history, fn (array $entry) => ! $this->isRedirect($entry)));
    }

    /**
     * Number of 3xx redirect hops recorded in history.
     */
    public function redirects(): int
    {
        return count(array_filter($this->history, fn (array $entry) => $this->isRedirect($entry)));
    }

    private function isRedirect(array $entry): bool
    {
        return $entry['response'] instanceof ResponseInterface
            && $entry['response']->getStatusCode() >= 300
            && $entry['response']->getStatusCode() < 400;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function useClient(array $config): void
    {
        $throttle = (int) ($this->api['throttle_ms'] ?? 250);

        $options = array_replace_recursive(
            ['timeout' => 20, 'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json']],
            $config['options'] ?? [],
        );

        $clientConfig = ['provider' => 'live', 'base_uri' => $this->api['base_uri'], 'options' => $options]
            + array_intersect_key($config, array_flip([
                'auth', 'retry', 'errors_key', 'update_method', 'query', 'grammar', 'pagination',
            ]));

        $repository = new Repository(['rest' => ['default' => 'live', 'clients' => ['live' => $clientConfig]]]);

        $manager = new ClientManager($repository, new ClientFactory);

        // ClientManager builds a fresh client per unique options hash (e.g. one per withHeaders() call),
        // so each client needs its own handler stack: pushing history/throttle onto a shared stack would
        // register them again on every new client and record/throttle each request multiple times.
        $manager->extend('live', function (array $clientConfig) use ($throttle) {
            $stack = HandlerStack::create();
            $clientConfig['options']['handler'] = $stack;

            $client = GuzzleClient::fromConfig($clientConfig);

            // Throttle and history sit inside auth and retry, closest to the transport,
            // recording every attempt with applied auth headers and its throttle delay.
            // Throttle wraps history (pushed first, so it's the outer of the two) so the
            // non-blocking delay it reserves is visible in the recorded options — a Pool
            // of concurrent requests (getMany, eager loads) mustn't all send at once, so
            // instead of blocking with usleep() it reserves a per-host send slot and lets
            // Guzzle's handler apply the wait asynchronously via the 'delay' option.
            $stack->push(fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $throttle) {
                $host = $request->getUri()->getHost();
                $now = microtime(true);
                $slot = max($now, self::$nextSlotAt[$host] ?? 0.0);
                self::$nextSlotAt[$host] = $slot + $throttle / 1000;
                $options['delay'] = max((int) ($options['delay'] ?? 0), (int) round(($slot - $now) * 1000));

                return $handler($request, $options);
            }, 'live_throttle');
            $stack->push(Middleware::history($this->history), 'live_history');

            return $client;
        });

        Model::setClientResolver($manager);
    }
}
