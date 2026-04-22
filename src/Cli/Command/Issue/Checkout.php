<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueForkAction;
use mglaman\DrupalOrg\Action\Issue\SetupIssueRemoteAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
        $gitLabClient = new GitLabClient();
        $action = new GetIssueForkAction($this->client, $gitLabClient);
        $fork = $action($this->nid);

        // Verify the remote exists locally; offer to set it up if missing.
        $checkRemote = new Process(['git', 'remote', 'get-url', $fork->remoteName]);
        $checkRemote->run();
        if (!$checkRemote->isSuccessful()) {
            $shouldSetup = true;
            if (self::$interactive) {
                /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    sprintf(
                        '<comment>Remote %s does not exist. Set it up now? [Y/n]</comment> ',
                        $fork->remoteName
                    ),
                    true
                );
                // ask() returns mixed; cast to bool and fall back to the default (true) on null.
                $shouldSetup = (bool) ($helper->ask($input, $output, $question) ?? true);
            }
            if (!$shouldSetup) {
                $this->stdErr->writeln(
                    sprintf(
                        '<error>Remote %s does not exist. Run `issue:setup-remote %s` first.</error>',
                        $fork->remoteName,
                        $this->nid
                    )
                );
                return 1;
            }
            try {
                $setupAction = new SetupIssueRemoteAction($this->client, $gitLabClient);
                $setupResult = $setupAction($this->nid);
            } catch (\RuntimeException $e) {
                $this->stdErr->writeln(
                    sprintf('<error>Failed to set up remote: %s</error>', $e->getMessage())
                );
                return 1;
            }
            $this->stdOut->writeln(
                sprintf('<info>Remote %s added and fetched.</info>', $setupResult->remoteName)
            );
            // Refresh fork data after setup so branches are populated.
            $fork = $action($this->nid);
        } else {
            // Remote already exists; fetch to ensure tracking refs are up-to-date.
            $fetchProcess = new Process(['git', 'fetch', $fork->remoteName]);
            $fetchProcess->run();
            if (!$fetchProcess->isSuccessful()) {
                $fetchOutput = trim($fetchProcess->getErrorOutput() . $fetchProcess->getOutput());
                $this->stdErr->writeln(
                    sprintf('<error>Failed to fetch remote %s: %s</error>', $fork->remoteName, $fetchOutput)
                );
                return 1;
            }
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
            if (count($issueBranches) === 1) {
                $branchArg = $issueBranches[0];
                $this->stdOut->writeln(sprintf('<info>Auto-selected branch %s.</info>', $branchArg));
            } elseif (!self::$interactive) {
                $this->stdErr->writeln('<error>No branch specified. Available branches:</error>');
                foreach ($issueBranches as $b) {
                    $this->stdErr->writeln('  ' . $b);
                }
                return 1;
            } else {
                /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new \Symfony\Component\Console\Question\ChoiceQuestion(
                    'Select a branch to check out:',
                    $issueBranches
                );
                $branchArg = $helper->ask($input, $output, $question);
            }
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
