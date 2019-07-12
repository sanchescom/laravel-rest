<?php

namespace App\Rest;

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
    public function get($id = '');

    /**
     * @param string $id
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function put($id = '', array $data = []);

    /**
     * @param string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function delete($id = '');

    /**
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function post(array $data = []);
}
