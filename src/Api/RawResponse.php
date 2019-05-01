<?php

namespace mglaman\DrupalOrg;

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
        if (property_exists($this->response, $key)) {
            return $this->response->{$key};
        }
        return null;
    }
}
