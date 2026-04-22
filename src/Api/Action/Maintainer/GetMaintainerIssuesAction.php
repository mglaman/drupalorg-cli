<?php

namespace mglaman\DrupalOrg\Action\Maintainer;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Enum\MaintainerIssueType;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;

class GetMaintainerIssuesAction implements ActionInterface
{
    public function __construct(private readonly ?\Closure $feedLoader = null)
    {
    }

    public function __invoke(string $user, MaintainerIssueType $type): MaintainerIssuesResult
    {
        $feedUrl = match ($type) {
            MaintainerIssueType::Rtbc => "https://www.drupal.org/project/user/$user/feed?status[0]=14",
            MaintainerIssueType::Any => "https://www.drupal.org/project/user/$user/feed",
        };

        $feed = ($this->feedLoader ?? static fn(string $u) => \Feed::load($u))($feedUrl);
        /** @var array{title: string, item: array<int, mixed>} $feedArray */
        $feedArray = $feed->toArray();

        $items = [];
        foreach ($feedArray['item'] as $item) {
            $project = '';
            $linkParts = parse_url($item['link']);
            if (is_array($linkParts) && isset($linkParts['path'])) {
                $pathParts = array_values(array_filter(explode('/', $linkParts['path'])));
                if (isset($pathParts[1])) {
                    $project = $pathParts[1];
                }
            }
            $items[] = ['project' => $project, 'title' => $item['title'], 'link' => $item['link']];
        }

        return new MaintainerIssuesResult(feedTitle: $feedArray['title'], items: $items);
    }
}
