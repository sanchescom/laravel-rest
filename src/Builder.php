<?php

namespace Sanchescom\Rest;

use Illuminate\Support\Arr;
use Sanchescom\Support\Json;

class Builder
{
    /**
     * The model being queried.
     *
     * @var \Sanchescom\Rest\Model
     */
    protected $model;

    /**
     * @param string $id
     *
     * @throws \Sanchescom\Support\Exceptions\UnableDecodeJsonException
     *
     * @return \Jenssegers\Model\Model|\Illuminate\Support\Collection|self
     */
    public function get($id = null)
    {
        $response = $this->model->getClient()->get($id);

        return $this->makeOneResult($response);
    }

    /**
     * @param string $id
     *
     * @throws \Sanchescom\Support\Exceptions\UnableDecodeJsonException
     *
     * @return ?\Jenssegers\Model\Model|\Illuminate\Support\Collection|self
     */
    public function getOneBy(array $filters = null)
    {
        $response = $this->model->getClient()->getOneBy($filters);

        return $response ? $this->makeOneResult($response) : null;
    }

    /**
     * @param $response
     * @return \Illuminate\Support\Collection|Model
     */
    private function makeOneResult($response) {
        $responseData = Arr::get($response, $this->model->getDataKey());
        if (!empty(($responseData[$this->model->getKeyName()]))) {
            return $this->model->newInstance(
                Arr::get($response, $this->model->getDataKey())
            );
        }

        return $this->hydrate($response);
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
        $this->model->fill($data);

        $attributes = $this->model->getAttributes();

        $response = $this->model->getClient()->put($id, $attributes);

        return $this->model->newInstance($response);
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function delete($id = null)
    {
        $this->model->getClient()->delete($id ?: $this->model->getKey());

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
        $this->model->fill($data);

        $attributes = $this->model->getAttributes();

        $response = $this->model->getClient()->post($attributes);

        return $this->model->newInstance($response);
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
        $responses = $this->model->getClient()->getMany(array_filter($ids));

        return $this->hydrate($responses);
    }

    /**
     * Set a model instance for the model being queried.
     *
     * @param  \Sanchescom\Rest\Model  $model
     *
     * @return self
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array  $items
     *
     * @return \Illuminate\Support\Collection
     */
    public function hydrate(array $items)
    {
        return $this->model->newCollection(array_map(function ($item) {
            return $this->model->newInstance()->fill($item);
        }, Arr::get($items, $this->model->getDataKey())));
    }
}
