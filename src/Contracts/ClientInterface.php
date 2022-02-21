<?php

namespace Sanchescom\Rest\Contracts;

interface ClientInterface
{
    /**
     * @param string|null $endpoint
     *
     * @return mixed
     */
    public function setEndpoint(?string $endpoint);

    /**
     * @param string $id
     *
     * @return ?[]
     */
    public function get($id = null);

    /**
     * @param array $filters
     * @return ?[]
     */
    public function getOneBy(array $filters);

    /**
     * @param array $ids
     *
     * @return [][]
     */
    public function getMany(array $ids = []);

    /**
     * @param string $id
     * @param array $data
     *
     * @return []
     */
    public function put($id = null, array $data = []);

    /**
     * @param string $id
     *
     * @return []
     */
    public function delete($id = null);

    /**
     * @param array $data
     *
     * @return []
     */
    public function post(array $data = []);
}
