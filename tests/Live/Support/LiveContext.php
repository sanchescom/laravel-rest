<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Support;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Config\Repository;
use Psr\Http\Message\RequestInterface;
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
    private static array $lastRequestAt = [];

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

    public function requests(): int
    {
        return count($this->history);
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

            // History and throttle sit inside auth and retry, closest to the transport,
            // recording every attempt with applied auth headers and throttling.
            $stack->push(Middleware::history($this->history), 'live_history');
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($throttle) {
                $host = $request->getUri()->getHost();
                $wait = (self::$lastRequestAt[$host] ?? 0.0) + $throttle / 1000 - microtime(true);

                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }

                self::$lastRequestAt[$host] = microtime(true);

                return $request;
            }), 'live_throttle');

            return $client;
        });

        Model::setClientResolver($manager);
    }
}
