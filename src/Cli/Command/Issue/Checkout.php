<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueForkAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Checkout extends IssueCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('issue:checkout')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->addArgument('branch', InputArgument::OPTIONAL, 'Branch name to check out')
            ->setDescription('Check out a branch from the GitLab issue fork.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = new GetIssueForkAction($this->client, new GitLabClient());
        $fork = $action($this->nid);

        // Verify the remote exists locally.
        $checkRemote = new Process(['git', 'remote', 'get-url', $fork->remoteName]);
        $checkRemote->run();
        if (!$checkRemote->isSuccessful()) {
            $this->stdErr->writeln(
                sprintf(
                    '<error>Remote %s does not exist. Run `issue:setup-remote %s` first.</error>',
                    $fork->remoteName,
                    $this->nid
                )
            );
            return 1;
        }

        // Filter branches to those starting with the issue NID.
        $issueBranches = array_values(array_filter(
            $fork->branches,
            fn(string $b) => str_starts_with($b, $this->nid . '-')
        ));

        $branchArg = (string) ($this->stdIn->getArgument('branch') ?? '');

        if ($branchArg === '') {
            if ($issueBranches === []) {
                $this->stdErr->writeln('<error>No branches found for this issue on the fork.</error>');
                return 1;
            }
            if (!self::$interactive) {
                $this->stdErr->writeln('<error>No branch specified. Available branches:</error>');
                foreach ($issueBranches as $b) {
                    $this->stdErr->writeln('  ' . $b);
                }
                return 1;
            }
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new \Symfony\Component\Console\Question\ChoiceQuestion(
                'Select a branch to check out:',
                $issueBranches
            );
            $branchArg = $helper->ask($input, $output, $question);
        }

        $checkoutProcess = new Process([
            'git', 'checkout', '-b', $branchArg, '--track',
            $fork->remoteName . '/' . $branchArg,
        ]);
        $checkoutProcess->run();

        if (!$checkoutProcess->isSuccessful()) {
            // Branch may already exist locally; try a plain checkout.
            $simpleCheckout = new Process(['git', 'checkout', $branchArg]);
            $simpleCheckout->run();
            if (!$simpleCheckout->isSuccessful()) {
                $this->stdErr->writeln('<error>Failed to check out branch:</error>');
                $this->stdErr->writeln($checkoutProcess->getErrorOutput());
                return 1;
            }
        }

        $this->stdOut->writeln(sprintf('<info>Checked out branch %s.</info>', $branchArg));
        return 0;
    }
}
