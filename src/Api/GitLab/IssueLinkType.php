<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

enum IssueLinkType: string
{
    case RelatesTo = 'relates_to';
    case Blocks = 'blocks';
    case IsBlockedBy = 'is_blocked_by';
}
