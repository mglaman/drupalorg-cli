<?php

namespace mglaman\DrupalOrg;

class Request
{
    const ASC = 'ASC';
    const DESC = 'DESC';

    protected $baseUri = Client::API_URL;
    protected $endpoint;
    protected $options = [];

    /**
     * Request constructor.
     *
     * @param string $endpoint
     * @param array $options
     */
    public function __construct($endpoint = '', array $options = [])
    {
        $this->endpoint = $endpoint;
        $this->options = $options;
    }

    /**
     * Get the request URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->baseUri . $this->endpoint . '?' . urldecode(http_build_query($this->getOptions()));
    }

    /**
     * Set the request endpoint.
     *
     * @param $endpoint
     * @return $this
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * Get the request options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set a request option.
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Set the key to sort key.
     *
     * @param $key
     * @return \mglaman\DrupalOrg\Request
     */
    public function setSort($key) {
        return $this->setOption('sort', $key);
    }

    /**
     * Set the direction order.
     *
     * @param $direction
     * @return \mglaman\DrupalOrg\Request
     */
    public function setDirection($direction) {
        return $this->setOption('direction', $direction);
    }
}