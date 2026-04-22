<?php

namespace mglaman\DrupalOrg\Enum;

enum ProjectIssueType: string
{
    case All = 'all';
    case Rtbc = 'rtbc';
    case Review = 'review';
}
