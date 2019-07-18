<?php

namespace App\Rest;

use App\Rest\Contracts\ClientResolverInterface as Resolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jenssegers\Model\Model as BaseModel;
use Sanchescom\Support\Json;

class Model extends BaseModel
{
    /**
     * The client resolver instance.
     *
     * @var \App\Rest\Contracts\ClientResolverInterface
     */
    protected static $resolver;

    /**
     * The client for the model.
     *
     * @var string|null
     */
    protected $client;

    /**
     * The endpoint associated with the model.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The options for request
     *
     * @var array
     */
    protected $options = [];

    /**
     * The options for request
     *
     * @var array
     */
    protected $query = [];

    /**
     * The options for request
     *
     * @var string
     */
    protected $dataKey;

    /**
     * Get the client for the model.
     *
     * @return \App\Rest\Contracts\ClientInterface
     */
    public function getClient()
    {
        return static::resolveClient(
            $this->getClientName(),
            $this->getEndpoint(),
            $this->getOptions()
        );
    }

    /**
     * Get the current client name for the model.
     *
     * @return string|null
     */
    public function getClientName()
    {
        return $this->client;
    }

    /**
     * Resolve a client instance.
     *
     * @param string|null $client
     * @param string|null $endpoint
     * @param array $options
     *
     * @return \App\Rest\Contracts\ClientInterface
     */
    public static function resolveClient($client = null, $endpoint = null, array $options = [])
    {
        $client = static::$resolver->client($client, $options);
        $client->setEndpoint($endpoint);

        return $client;
    }

    /**
     * Get the endpoint associated with the model.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Set the endpoint associated with the model.
     *
     * @param string $endpoint
     *
     * @return $this
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Set the client resolver instance.
     *
     * @param \App\Rest\Contracts\ClientResolverInterface $resolver
     *
     * @return void
     */
    public static function setClientResolver(Resolver $resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * Create a new Collection instance.
     *
     * @param array $models
     *
     * @return \Illuminate\Support\Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * @param string $id
     *
     * @throws \Sanchescom\Support\Exceptions\UnableDecodeJsonException
     *
     * @return \Jenssegers\Model\Model|\Illuminate\Support\Collection|self
     */
    public function get($id = null)
    {
        $response = Json::asArray(
            $this->getClient()->get($id)->getContent()
        );

        if ($this->dataKey){
            $response = Arr::get($response, $this->dataKey);
        }

        if ($id !== null) {
            return $this->newInstance($response);
        }

        return $this->newCollection(self::hydrate($response));
    }

    /**
     * @param string $id
     * @param array $data
     *
     * @throws \Sanchescom\Support\Exceptions\UnableDecodeJsonException
     *
     * @return \Jenssegers\Model\Model|self
     */
    public function put($id = null, array $data = [])
    {
        $this->fill($data);

        $response = $this
            ->getClient()
            ->put($id, $this->getAttributes())
            ->getContent();

        return $this->newInstance(Json::asArray($response));
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function delete($id = null)
    {
        if ($this->getKey()) {
            $id = $this->getKey();
        }

        $this->getClient()->delete($id);

        return true;
    }

    /**
     * @param array $data
     *
     * @throws \Sanchescom\Support\Exceptions\UnableDecodeJsonException
     *
     * @return \Jenssegers\Model\Model|self
     */
    public function post(array $data = [])
    {
        $this->fill($data);

        $response = $this
            ->getClient()
            ->post($this->getAttributes())
            ->getContent();

        return $this->newInstance(Json::asArray($response));
    }

    /**
     * @param array $ids
     *
     * @throws \Sanchescom\Support\Exceptions\UnableDecodeJsonException
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMany(array $ids = [])
    {
        $items = [];

        foreach ($ids as $id) {
            $items[] = $this->get($id);
        }

        return $this->newCollection($items);
    }
}
