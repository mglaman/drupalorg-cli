<?php

namespace mglaman\DrupalOrg\Action\Issue;

use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueComment;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use Psr\Http\Message\ResponseInterface;

class GetIssueAction implements ActionInterface
{
    private const COMMENT_CONCURRENCY = 5;

    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(string $nid, bool $withComments = false): IssueResult
    {
        $issue = $this->client->getNode($nid);

        if (!$withComments || $issue->comments === []) {
            return IssueResult::fromIssueNode($issue);
        }

        // Build an ordered list of [cid, uri] pairs from the comment references.
        // We use a plain list (not a cid-keyed map) because PHP coerces numeric
        // string array keys to int, which would break typed Pool callbacks.
        $commentRefs = [];
        foreach ($issue->comments as $ref) {
            $cid = (string) ($ref->id ?? '');
            if ($cid === '') {
                continue;
            }
            $uri = (string) ($ref->uri ?? '');
            if ($uri === '') {
                $uri = sprintf('%scomment/%s', Client::API_URL, $cid);
            }
            $commentRefs[] = ['cid' => $cid, 'uri' => $uri];
        }

        if ($commentRefs === []) {
            return IssueResult::fromIssueNode($issue);
        }

        $requests = static function () use ($commentRefs): \Generator {
            foreach ($commentRefs as $index => $ref) {
                yield $index => new GuzzleRequest('GET', $ref['uri']);
            }
        };

        $commentsByIndex = [];
        $pool = new Pool($this->client->getGuzzleClient(), $requests(), [
            'concurrency' => self::COMMENT_CONCURRENCY,
            'fulfilled' => static function (ResponseInterface $response, int $index) use (&$commentsByIndex): void {
                try {
                    $data = json_decode(
                        (string) $response->getBody(),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                    $comment = IssueComment::fromStdClass($data);
                    if ($comment->authorId === '180064') {
                        return;
                    }
                    $commentsByIndex[$index] = $comment;
                } catch (\JsonException) {
                    // skip unparseable responses
                }
            },
            'rejected' => static function (\Throwable $reason, int $index): void {
                // skip failed comment fetches
            },
        ]);
        $pool->promise()->wait();

        // Restore original ordering from the issue comment refs.
        $comments = [];
        foreach (array_keys($commentRefs) as $index) {
            if (isset($commentsByIndex[$index])) {
                $comments[] = $commentsByIndex[$index];
            }
        }

        return IssueResult::fromIssueNode($issue, $comments);
    }
}
