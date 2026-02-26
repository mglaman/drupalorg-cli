<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\Result\ResultInterface;

interface FormatterInterface
{
    public function format(ResultInterface $result): string;
}
