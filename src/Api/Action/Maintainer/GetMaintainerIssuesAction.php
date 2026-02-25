<?php

namespace mglaman\DrupalOrg\Action\Maintainer;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;

class GetMaintainerIssuesAction implements ActionInterface
{
    public function __construct(private readonly ?\Closure $feedLoader = null)
    {
    }

    public function __invoke(string $user, string $type): MaintainerIssuesResult
    {
        $feedUrl = match ($type) {
            'rtbc' => "https://www.drupal.org/project/user/$user/feed?status[0]=14",
            default => "https://www.drupal.org/project/user/$user/feed",
        };

        $feed = ($this->feedLoader ?? static fn(string $u) => \Feed::load($u))($feedUrl);
        /** @var array{title: string, item: array<int, mixed>} $feedArray */
        $feedArray = $feed->toArray();

        $items = [];
        foreach ($feedArray['item'] as $item) {
            $linkParts = parse_url($item['link']);
            $pathParts = array_values(array_filter(explode('/', $linkParts['path'])));
            $items[] = ['project' => $pathParts[1], 'title' => $item['title'], 'link' => $item['link']];
        }

        return new MaintainerIssuesResult(feedTitle: $feedArray['title'], items: $items);
    }
}
