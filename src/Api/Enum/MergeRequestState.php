<?php

namespace mglaman\DrupalOrg\Enum;

enum MergeRequestState: string
{
    case Opened = 'opened';
    case Closed = 'closed';
    case Merged = 'merged';
    case All = 'all';
}
