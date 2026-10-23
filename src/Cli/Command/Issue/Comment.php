<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\PostWorkItemCommentAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Comment extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('issue:comment')
            ->addArgument('ref', InputArgument::REQUIRED, 'Work item ref: NID, project#nid, or full URL')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Comment body.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Read the comment body from a file. Use - to read from stdin.')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: text, json, md, llm.', 'text')
            ->setDescription('Post a comment on a GitLab work item.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ref = (string) $this->stdIn->getArgument('ref');
        $body = $this->readBody($input);

        $action = new PostWorkItemCommentAction($this->client, $this->createGitLabClient());
        $result = $action($ref, $body);

        $format = (string) $this->stdIn->getOption('format');
        if ($this->writeFormatted($result, $format)) {
            return 0;
        }
        $this->stdOut->writeln(sprintf('Posted comment: %s', $result->noteUrl()));
        return 0;
    }

    protected function createGitLabClient(): GitLabClient
    {
        return new GitLabClient();
    }

    private function readBody(InputInterface $input): string
    {
        $message = $input->getOption('message');
        $file = $input->getOption('file');
        if (($message === null) === ($file === null)) {
            throw new \InvalidArgumentException('Pass exactly one of --message or --file.');
        }
        if (is_string($message)) {
            return $message;
        }

        $file = (string) $file;
        if ($file === '-') {
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $contents = stream_get_contents($stream ?? STDIN);
        } else {
            $contents = is_readable($file) ? file_get_contents($file) : false;
        }
        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf('Could not read the comment body from %s.', $file));
        }
        return $contents;
    }
}
