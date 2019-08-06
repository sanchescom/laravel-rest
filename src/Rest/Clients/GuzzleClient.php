<?php

namespace App\Rest\Clients;

use App\Rest\Contracts\ClientInterface;
use App\Rest\Support\Arr;
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

    /**
     * GuzzleClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    /**
     * @param string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function get($id = null)
    {
        $response = $this->client->get($this->getEndpoint($id));

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param array $ids
     *
     * @return \Illuminate\Http\Response[]
     */
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
                $responses[] = response($response->getBody(), $response->getStatusCode());
            }
        ]);

        $promise = $pool->promise();

        $promise->wait();

        return $responses;
    }

    /**
     * @param string $id
     * @param array $data
     *
     * @throws \Sanchescom\Support\Exceptions\UnableEncodeJsonException
     *
     * @return \Illuminate\Http\Response
     */
    public function put($id = null, array $data = [])
    {
        $response = $this->client->put($this->getEndpoint($id), ['body' => Arr::asJson($data)]);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param array $data
     *
     * @throws \Sanchescom\Support\Exceptions\UnableEncodeJsonException
     *
     * @return \Illuminate\Http\Response
     */
    public function post(array $data = [])
    {
        $response = $this->client->post($this->getEndpoint(), ['body' => Arr::asJson($data)]);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function delete($id = null)
    {
        $response = $this->client->delete($this->getEndpoint($id));

        return response($response->getBody(), $response->getStatusCode());
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
     * @return \App\Rest\Contracts\ClientInterface
     */
    public function setEndpoint(?string $endpoint): ClientInterface
    {
        $this->endpoint = $endpoint;

        return $this;
    }
}
