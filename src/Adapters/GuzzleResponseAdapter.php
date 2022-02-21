<?php

namespace Sanchescom\Rest\Adapters;

use Sanchescom\Rest\Contracts\ResponseAdapterInterface;
use Sanchescom\Support\Json;

class GuzzleResponseAdapter implements ResponseAdapterInterface
{
    /**
     * @param \Illuminate\Http\Response $notAnArray
     * @return array
     */
    public function arraify($notAnArray): array {
        return Json::asArray($notAnArray->getContent());
    }
}
