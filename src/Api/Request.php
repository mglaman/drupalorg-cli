<?php

namespace mglaman\DrupalOrg;

class Request
{
    public const ASC = 'ASC';
    public const DESC = 'DESC';

    protected string $baseUri = Client::API_URL;
    protected string $endpoint;
    protected array $options = [];

    /**
     * Request constructor.
     *
     * @param string $endpoint
     * @param array $options
     */
    public function __construct(string $endpoint = '', array $options = [])
    {
        $this->endpoint = $endpoint;
        $this->options = $options;
    }

    /**
     * Get the request URL.
     *
     * @return string
     */
    public function getUrl(): string {
        return $this->baseUri . $this->endpoint . '?' . urldecode(http_build_query($this->getOptions()));
    }

    public function setEndpoint(string $endpoint): self {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * Get the request options.
     *
     * @return array
     */
    public function getOptions(): array {
        return $this->options;
    }

    public function setOption($key, $value): self {
        $this->options[$key] = $value;
        return $this;
    }

    public function setSort(string $key): self {
        return $this->setOption('sort', $key);
    }

    public function setDirection(string $direction): self {
        assert($direction === self::ASC || $direction === self::DESC);
        return $this->setOption('direction', $direction);
    }
}
