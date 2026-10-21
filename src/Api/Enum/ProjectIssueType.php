<?php

namespace mglaman\DrupalOrg\Enum;

enum ProjectIssueType: string
{
    case All = 'all';
    case Rtbc = 'rtbc';
    case Review = 'review';

    public function gitLabLabel(): ?string
    {
        return match ($this) {
            ProjectIssueType::All => null,
            ProjectIssueType::Rtbc => 'state::rtbc',
            ProjectIssueType::Review => 'state::needsReview',
        };
    }
}
