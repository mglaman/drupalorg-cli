<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\Action\Maintainer\GetMaintainerReleaseNotesAction;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Release note command
 *
 * Referenced off of `grn`. https://www.drupal.org/project/grn
 */
class ReleaseNotes extends Command
{

    /**
     * @var \CzProject\GitPhp\GitRepository
     */
    protected GitRepository $repository;

    protected string $cwd;

    protected function configure(): void
    {
        $this
            ->setName('maintainer:release-notes')
            ->setAliases(['rn', 'mrn'])
            ->addArgument(
                'ref1',
                InputArgument::OPTIONAL,
                'Git tag, branch, or SHA'
            )
            ->addArgument(
                'ref2',
                InputArgument::OPTIONAL,
                'Git tag, branch, or SHA',
                'HEAD'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: json, markdown (md), html. Defaults to HTML.',
                'html'
            )
            ->setDescription('Generate release notes.');
    }

    protected function initialize(
        InputInterface $input,
        OutputInterface $output
    ): void {
        parent::initialize($input, $output);
        try {
            $process = new Process(['git', 'rev-parse', '--show-toplevel']);
            $process->run();
            $repository_dir = trim($process->getOutput());
            $this->cwd = $repository_dir;
            $client = new Git();
            $this->repository = $client->open($this->cwd);
        } catch (\Exception $e) {
            $this->stdOut->writeln('You must run this from a Git repository');
            exit(1);
        }
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $ref1 = $this->stdIn->getArgument('ref1');
        $ref2 = $this->stdIn->getArgument('ref2');

        if ($ref1 === null || $ref1 === '') {
            // @todo
            $this->stdOut->writeln('Please provide both arguments, for now.');
            return 1;
        }

        $format = $this->stdIn->getOption('format');

        $action = new GetMaintainerReleaseNotesAction($this->client);
        try {
            $result = $action($this->repository, $this->cwd, $ref1, $ref2);
        } catch (\InvalidArgumentException $e) {
            $this->stdOut->writeln($e->getMessage());
            return 1;
        } catch (\RuntimeException $e) {
            $this->stdOut->writeln($e->getMessage());
            return 1;
        }

        $project = $result->project;
        $ref1url = "https://www.drupal.org/project/{$project}/releases/$ref1";

        switch ($format) {
            case 'json':
                $this->stdOut->writeln(
                    json_encode($result->categorizedChanges, JSON_PRETTY_PRINT)
                );
                break;

            case 'markdown':
            case 'md':
                $this->stdOut->writeln('/Add a summary here/');
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf('### Contributors (%s)', count($result->contributors))
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    implode(
                        ', ',
                        array_map(
                            function ($username) use ($format): string {
                                return $this->formatUsername($username, $format);
                            },
                            array_keys($result->contributors)
                        )
                    )
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln('### Changelog');
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf(
                        '**Issues**: %s issues resolved.',
                        count($result->nidList)
                    )
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf('Changes since [%s](%s):', $ref1, $ref1url)
                );
                $this->stdOut->writeln('');
                foreach ($result->categorizedChanges as $changeCategory => $changeCategoryItems) {
                    $this->stdOut->writeln(sprintf('#### %s', $changeCategory));
                    $this->stdOut->writeln('');
                    foreach ($changeCategoryItems as $change) {
                        $this->stdOut->writeln(sprintf('* %s', $this->formatLine($change, $format)));
                    }
                    $this->stdOut->writeln('');
                }
                if ($result->changeRecords !== []) {
                    $this->stdOut->writeln('### Change Records');
                    $this->stdOut->writeln('');
                    foreach ($result->changeRecords as $record) {
                        if ($record->url !== '') {
                            $this->stdOut->writeln(sprintf('* [%s](%s)', $record->title, $record->url));
                        } else {
                            $this->stdOut->writeln(sprintf('* %s', $record->title));
                        }
                    }
                    $this->stdOut->writeln('');
                }
                break;

            case 'html':
            default:
                $this->stdOut->writeln('<p><em>Add a summary here</em></p>');
                $this->stdOut->writeln(
                    sprintf('<h3>Contributors (%s)</h3>', count($result->contributors))
                );
                $this->stdOut->writeln(
                    sprintf(
                        '<p>%s</p>',
                        implode(
                            ', ',
                            array_map(
                                function ($username) use ($format): string {
                                    return $this->formatUsername($username, $format);
                                },
                                array_keys($result->contributors)
                            )
                        )
                    )
                );
                $this->stdOut->writeln('<h3>Changelog</h3>');
                $this->stdOut->writeln(
                    sprintf(
                        '<p><strong>Issues:</strong> %s issues resolved.</p>',
                        count($result->nidList)
                    )
                );
                $this->stdOut->writeln(
                    sprintf(
                        '<p>Changes since <a href="%s">%s</a>:</p>',
                        $ref1url,
                        $ref1
                    )
                );

                foreach ($result->categorizedChanges as $changeCategory => $changeCategoryItems) {
                    $this->stdOut->writeln(
                        sprintf('<h4>%s</h4>', $changeCategory)
                    );
                    $this->stdOut->writeln('<ul>');
                    foreach ($changeCategoryItems as $change) {
                        $this->stdOut->writeln(
                            sprintf('  <li>%s</li>', $this->formatLine($change, $format))
                        );
                    }
                    $this->stdOut->writeln('</ul>');
                }

                if ($result->changeRecords !== []) {
                    $this->stdOut->writeln('<h3>Change Records</h3>');
                    $this->stdOut->writeln('<ul>');
                    foreach ($result->changeRecords as $record) {
                        if ($record->url !== '') {
                            $this->stdOut->writeln(sprintf('  <li><a href="%s">%s</a></li>', htmlspecialchars($record->url), htmlspecialchars($record->title)));
                        } else {
                            $this->stdOut->writeln(sprintf('  <li>%s</li>', htmlspecialchars($record->title)));
                        }
                    }
                    $this->stdOut->writeln('</ul>');
                }

                break;
        }
        return 0;
    }

    protected function formatUsername(string $user, string $format): string
    {
        $baseUrl = 'https://www.drupal.org/u/%1$s';
        $userAlias = str_replace(' ', '-', mb_strtolower($user));
        if ($format === 'html') {
            $replacement = '<a href="' . $baseUrl . '">%2$s</a>';
        } elseif ($format === 'markdown' || $format === 'md') {
            $replacement = '[%2$s](' . $baseUrl . ')';
        } else {
            $replacement = '%2$s';
        }
        return sprintf($replacement, $userAlias, $user);
    }

    protected function formatLine(string $value, string $format): string
    {
        $baseUrl = 'https://www.drupal.org/node/$1';

        if ($format === 'html') {
            $replacement = sprintf('<a href="%s">#$1</a>', $baseUrl);
        } elseif ($format === 'markdown' || $format === 'md') {
            $replacement = sprintf('[#$1](%s)', $baseUrl);
        } else {
            $replacement = '#$1';
        }

        return preg_replace('/#(\d+)/S', $replacement, $value);
    }
}
