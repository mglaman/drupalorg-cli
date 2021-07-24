<?php

namespace mglaman\DrupalOrg;

class Response extends RawResponse
{

    protected array $validLinks = ['self', 'first', 'last', 'next'];

    public function getLink($link)
    {
        if (!in_array($link, $this->validLinks, true)) {
            throw new \InvalidArgumentException('Invalid link type');
        }

        return $this->get($link);
    }

    public function getList(): \ArrayObject {
        return new \ArrayObject($this->get('list'));
    }
}
