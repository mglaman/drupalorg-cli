<?php

namespace mglaman\DrupalOrg;

class Response extends RawResponse {

  protected $validLinks = ['self', 'first', 'last', 'next'];

  public function getLink($link) {
    if (!in_array($link, $this->validLinks)) {
      throw new \InvalidArgumentException('Invalid link type');
    }

    return $this->get($link);
  }

  public function getList() {
    return new \ArrayObject($this->get('list'));
  }

}