<?php

namespace mglaman\DrupalOrg;

class RawResponse
{
    /**
     * @var \stdClass
     */
    protected $response;

    public function __construct(string $response)
    {
        $this->response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return mixed|null
     */
    public function get(string $key)
    {
        if (property_exists($this->response, $key)) {
            return $this->response->{$key};
        }
        return null;
    }

    /**
     * Get the full response body.
     *
     * @return mixed
     *   The JSON parsed body in the response.
     */
    public function getContent()
    {
        return $this->response;
    }
}
