<?php

namespace App\Rest\Contracts;

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
     * @return \Illuminate\Http\Response
     */
    public function get($id = null);

    /**
     * @param string $id
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function put($id = null, array $data = []);

    /**
     * @param string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function delete($id = null);

    /**
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function post(array $data = []);
}
