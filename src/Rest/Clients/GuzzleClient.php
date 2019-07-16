<?php

namespace App\Rest\Clients;

use App\Rest\Contracts\ClientInterface;
use GuzzleHttp\Client;

class GuzzleClient implements ClientInterface
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

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
        $response = $this->client->get($this->getEndpoint() . '/' . $id);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param string $id
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function put($id = null, array $data = [])
    {
        $response = $this->client->put($this->getEndpoint() . '/' . $id, ['body' => json_encode($data)]);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function post(array $data = [])
    {
        $response = $this->client->post($this->getEndpoint(), ['body' => json_encode($data)]);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function delete($id = null)
    {
        $response = $this->client->delete($this->getEndpoint() . '/' . $id);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @return string|null
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * @param string|null $endpoint
     */
    public function setEndpoint(?string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }
}
