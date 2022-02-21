<?php

namespace Sanchescom\Rest\Clients;

use Sanchescom\Rest\Adapters\GuzzleResponseAdapter;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Support\Arr;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;

class GuzzleClient implements ClientInterface
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var \GuzzleHttp\Pool
     */
    protected $pool;

    /** @var string|null */
    protected $endpoint;

    /** @var GuzzleResponseAdapter  */
    private GuzzleResponseAdapter $responseAdapter;

    /**
     * GuzzleClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [], GuzzleResponseAdapter $responseAdapter)
    {
        $this->client = new Client($config);
        $this->responseAdapter = $responseAdapter;
    }

    /** @inheritdoc */
    public function get($id = null)
    {
        $response = $this->client->get($this->getEndpoint($id));

        return $this->responseAdapter->arraify($response->getBody());
    }


    /** @inheritdoc  */
    public function getOneBy(array $filters) {
        $response = $this->client->get($this->endpoint . '?' . http_build_query($filters));

        return $this->responseAdapter->arraify($response->getBody());
    }

    /** @inheritdoc */
    public function getMany(array $ids = [])
    {
        $requests = function ($ids) {
            foreach ($ids as $id) {
                yield function () use ($id) {
                    return $this->client->getAsync($this->getEndpoint($id));
                };
            }
        };

        $responses = [];

        $pool = new Pool($this->client, $requests($ids), [
            'fulfilled' => function (Response $response) use (&$responses) {
                $responses[] = $this->responseAdapter->arraify($response->getBody(), $response->getStatusCode());
            }
        ]);

        $promise = $pool->promise();

        $promise->wait();

        return $responses;
    }


    /** @inheritdoc */
    public function put($id = null, array $data = [])
    {
        $response = $this->client->put($this->getEndpoint($id), ['body' => Arr::asJson($data)]);

        return $this->responseAdapter->arraify($response->getBody());
    }

    /** @inheritdoc */
    public function post(array $data = [])
    {
        $response = $this->client->post($this->getEndpoint(), ['body' => Arr::asJson($data)]);

        return $this->responseAdapter->arraify($response->getBody());
    }

    /** @inheritdoc */
    public function delete($id = null)
    {
        $response = $this->client->delete($this->getEndpoint($id));

        return $this->responseAdapter->arraify($response->getBody());
    }

    /**
     * @param null $id
     *
     * @return string|null
     */
    public function getEndpoint($id = null): ?string
    {
        if ($id !== null) {
            return $this->endpoint . '/' . $id;
        }

        return $this->endpoint;
    }

    /**
     * @param string|null $endpoint
     *
     * @return \Sanchescom\Rest\Contracts\ClientInterface
     */
    public function setEndpoint(?string $endpoint): ClientInterface
    {
        $this->endpoint = $endpoint;

        return $this;
    }
}
