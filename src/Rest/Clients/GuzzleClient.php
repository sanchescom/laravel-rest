<?php

namespace App\Rest\Clients;

use App\Rest\ClientInterface;
use GuzzleHttp\Client;
use Illuminate\Http\Response;

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
     * @return Response
     */
    public function get($id = '')
    {
        $response = $this->client->get($this->getEndpoint() . '/' . $id);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param string $id
     * @param array $data
     *
     * @return Response
     */
    public function put($id = '', array $data = [])
    {
        $response = $this->client->put($this->getEndpoint() . '/' . $id, ['body' => json_encode($data)]);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param array $data
     *
     * @return Response
     */
    public function post(array $data = [])
    {
        $response = $this->client->post($this->getEndpoint(), ['body' => json_encode($data)]);

        return response($response->getBody(), $response->getStatusCode());
    }

    /**
     * @param string $id
     *
     * @return Response
     */
    public function delete($id = '')
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
