<?php

namespace mglaman\DrupalOrg\Tests;

use mglaman\DrupalOrg\RawResponse;
use PHPUnit\Framework\TestCase;

class RawResponseTest extends TestCase
{

    public function testGet(): void
    {
        $sut = new RawResponse('{"message": "foobar"}');
        self::assertEquals('foobar', $sut->get('message'));
        self::assertEquals(null, $sut->get('baz'));
    }
}
