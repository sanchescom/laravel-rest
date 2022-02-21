<?php


namespace Sanchescom\Rest\Contracts;


interface ResponseAdapterInterface
{
    public function arraify($notAnArray): array;
}
