<?php

namespace Sanchescom\Rest;

use Sanchescom\Rest\Contracts\ClientResolverInterface as Resolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Jenssegers\Model\Model as BaseModel;

/**
 * Class Model.
 * @method static BaseModel|Collection get($id = null)
 * @method static BaseModel post(array $data = [])
 * @method static BaseModel put($id = null, array $data = [])
 * @method static BaseModel delete($id = null)
 * @method static Collection getMany(array $ids = [])
 */
class Model extends BaseModel
{
    use ForwardsCalls;

    /**
     * The client resolver instance.
     *
     * @var \Sanchescom\Rest\Contracts\ClientResolverInterface
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
     * The property name of data response.
     *
     * @var string
     */
    protected $dataKey;

    /**
     * The options for request
     *
     * @var array
     */
    protected $options = [];

    /**
     * Get the client for the model.
     *
     * @return \Sanchescom\Rest\Contracts\ClientInterface
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
     * @return \Sanchescom\Rest\Contracts\ClientInterface
     */
    public static function resolveClient($client = null, $endpoint = null, array $options = [])
    {
        return static::$resolver->client($client, $options)->setEndpoint($endpoint);
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
     * @param \Sanchescom\Rest\Contracts\ClientResolverInterface $resolver
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
     * @return \Sanchescom\Rest\Builder
     */
    public function newBuilder()
    {
        return new Builder();
    }

    /**
     * @return \Sanchescom\Rest\Builder
     */
    public function newModel()
    {
        return $this->newBuilder()->setModel($this);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        return $this->forwardCallTo($this->newModel(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * @return string|null
     */
    public function getDataKey()
    {
        return $this->dataKey;
    }
}
