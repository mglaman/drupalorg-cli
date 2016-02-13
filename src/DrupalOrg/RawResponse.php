<?php

namespace mglaman\DrupalOrgCli\DrupalOrg;

class RawResponse {
    /**
     * @var \stdClass
     */
    protected $response;

    public function __construct($response)
    {
        $this->response = json_decode($response);
    }

    public function get($key) {
        return $this->response->{$key};
    }
}