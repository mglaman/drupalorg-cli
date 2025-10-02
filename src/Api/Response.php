<?php

namespace mglaman\DrupalOrg;

class Response extends RawResponse
{

    /** @var string[] */
    protected array $validLinks = ['self', 'first', 'last', 'next'];

    public function getLink(string $link): string
    {
        if (!in_array($link, $this->validLinks, true)) {
            throw new \InvalidArgumentException('Invalid link type');
        }

        return $this->get($link);
    }

    /**
     * @return \ArrayObject<int, object>
     */
    public function getList(): \ArrayObject
    {
        return new \ArrayObject($this->get('list'));
    }

    /**
     * @return \ArrayObject<int, object>
     */
    public function getAll(): \ArrayObject
    {
        return new \ArrayObject($this->response);
    }
}
