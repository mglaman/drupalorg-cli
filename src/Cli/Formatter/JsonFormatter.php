<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\Result\ResultInterface;

class JsonFormatter implements FormatterInterface
{
    public function format(ResultInterface $result): string
    {
        return json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
