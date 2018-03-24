<?php

namespace Kily\Tools1C\OData;

use Psr\Http\Message\ResponseInterface;

class Response
{
    protected $response;
    protected $clnt;

    private $arr;

    public function __construct(Client $clnt, ResponseInterface $resp) {
        $this->client = $clnt;
        $this->response = $resp;
    }

    public function __toString() {
        return $this->response->getBody();
    }

    public function toArray() {
        if(!$this->arr)
            $this->arr = json_decode($this->response->getBody(),true);
        return $this->arr;
    }

    public function values() {
        $arr = $this->toArray();
        return $arr['value'] ?? [];
    }

}
