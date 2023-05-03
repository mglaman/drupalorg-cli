<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
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

    /**
     * @var array<string, string>
     */
    protected array $nids = [];

    /**
     * @var array<string, int>
     */
    protected array $users = [];

    /**
     * @var array<int, string>
     */
    protected array $categoryLabelMap = [
        0 => 'Misc',
        1 => 'Bug',
        2 => 'Task',
        3 => 'Feature',
        4 => 'Support',
        5 => 'Plan',
    ];

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
        $tags = $this->repository->getTags();
        if (!$this->stdIn->hasArgument('ref1')) {
            // @todo
            $this->stdOut->writeln('Please provide both arguments, for now.');
            return 1;
        }

        if (!in_array($ref1, $tags, true)) {
            $this->stdOut->writeln(sprintf('The %s tag is not valid.', $ref1));
            return 1;
        }
        if (($ref2 !== 'HEAD') && !in_array($ref2, $tags, true)) {
            $this->stdOut->writeln(sprintf('The %s tag is not valid.', $ref2));
            return 1;
        }

        $gitLog = $this->runProcess([
            'git',
            'log',
            '-s',
            '--pretty=format:%s',
            "$ref1..$ref2",
        ]);
        if ($gitLog->getExitCode() !== 0) {
            var_export($gitLog->getErrorOutput());
            $this->stdOut->writeln('Error getting commit log');
            return 1;
        }

        $format = $this->stdIn->getOption('format');

        $changes = array_filter(explode(PHP_EOL, trim($gitLog->getOutput())));

        $processedChanges = [];
        foreach ($changes as $change) {
            $nidsMatches = [];
            preg_match('/#(\d+)/S', $change, $nidsMatches);

            if (isset($nidsMatches[1]) && !isset($this->nids[$nidsMatches[1]])) {
                $this->nids[$nidsMatches[1]] = $nidsMatches[1];
                $issue = $this->client->getNode($nidsMatches[1]);
                // There should always be an issue category, but if not default to `Task.`
                $issueCategory = $issue->get('field_issue_category') ?? 'Task';
                $issueCategoryLabel = $this->categoryLabelMap[$issueCategory];
                $processedChanges[$issueCategoryLabel][$nidsMatches[1]] = $this->formatLine(
                    $change,
                    $format
                );
            }
        }
        ksort($processedChanges);

        // Work out what the project name is.
        $project = trim($this->getProjectName());
        if ($project === '') {
            return 1;
        }
        $ref1url = "https://www.drupal.org/project/{$project}/releases/$ref1";

        switch ($format) {
            case 'json':
                $this->stdOut->writeln(
                    json_encode($processedChanges, JSON_PRETTY_PRINT)
                );
                break;

            case 'markdown':
            case 'md':
                $this->stdOut->writeln('/Add a summary here/');
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf('### Contributors (%s)', count($this->users))
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    implode(
                        ', ',
                        array_map(
                            function ($username) use ($format): string {
                                return $this->formatUsername(
                                    $username,
                                    $format
                                );
                            },
                            array_keys($this->users)
                        )
                    )
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln('### Changelog');
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf(
                        '**Issues**: %s issues resolved.',
                        count($this->nids)
                    )
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf('Changes since [%s](%s):', $ref1, $ref1url)
                );
                $this->stdOut->writeln('');
                foreach ($processedChanges as $changeCategory => $changeCategoryItems) {
                    $this->stdOut->writeln(sprintf('#### %s', $changeCategory));
                    $this->stdOut->writeln('');
                    foreach ($changeCategoryItems as $change) {
                        $this->stdOut->writeln(sprintf('* %s', $change));
                    }
                    $this->stdOut->writeln('');
                }
                break;

            case 'html':
            default:
                $this->stdOut->writeln('<p><em>Add a summary here</em></p>');
                $this->stdOut->writeln(
                    sprintf('<h3>Contributors (%s)</h3>', count($this->users))
                );
                $this->stdOut->writeln(
                    sprintf(
                        '<p>%s</p>',
                        implode(
                            ', ',
                            array_map(
                                function ($username) use ($format): string {
                                    return $this->formatUsername(
                                        $username,
                                        $format
                                    );
                                },
                                array_keys($this->users)
                            )
                        )
                    )
                );
                $this->stdOut->writeln('<h3>Changelog</h3>');
                $this->stdOut->writeln(
                    sprintf(
                        '<p><strong>Issues:</strong> %s issues resolved.</p>',
                        count($this->nids)
                    )
                );
                $this->stdOut->writeln(
                    sprintf(
                        '<p>Changes since <a href="%s">%s</a>:</p>',
                        $ref1url,
                        $ref1
                    )
                );

                foreach ($processedChanges as $changeCategory => $changeCategoryItems) {
                    $this->stdOut->writeln(
                        sprintf('<h4>%s</h4>', $changeCategory)
                    );
                    $this->stdOut->writeln('<ul>');
                    foreach ($changeCategoryItems as $change) {
                        $this->stdOut->writeln(
                            sprintf('  <li>%s</li>', $change)
                        );
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
        $value = preg_replace('/^(Patch |- |Issue ){0,3}/', '', $value);

        $baseUrl = 'https://www.drupal.org/node/$1';

        if ($format === 'html') {
            $replacement = sprintf('<a href="%s">#$1</a>', $baseUrl);
        } elseif ($format === 'markdown' || $format === 'md') {
            $replacement = sprintf('[#$1](%s)', $baseUrl);
        } else {
            $replacement = '#$1';
        }

        $value = preg_replace('/#(\d+)/S', $replacement, $value);

        // Anything between by and ':' is a comma-separated list of usernames.
        $value = preg_replace_callback(
            '/by ([^:]+):/S',
            function (array $matches) use ($format): string {
                $out = [];
                // Separate the different usernames.
                foreach (explode(',', $matches[1]) as $user) {
                    $user = trim($user);

                    if (!isset($this->users[$user])) {
                        $this->users[$user] = 1;
                    } else {
                        $this->users[$user]++;
                    }

                    $out[] = $this->formatUsername($user, $format);
                }

                return 'by ' . implode(', ', $out) . ':';
            },
            $value
        );

        return $value;
    }

    /**
     * Extract the project name from the current git repository.
     *
     * @return string
     *   The d.o project name.
     */
    protected function getProjectName(): string
    {
        // Execute the command "git config --get remote.origin.url".
        $gitCmd = $this->runProcess(['git', 'config', '--get', 'remote.origin.url']);
        if ($gitCmd->getExitCode() !== 0) {
            $this->stdOut->writeln(
                "The 'git config' command returned an error."
            );
            return '';
        }

        // Check to see if this is a drupal.org project. If not, the remote origin
        // may be on GitHub. So just use the directory name.
        if (!strpos($gitCmd->getOutput(), 'drupal.org')) {
            $parts = explode(DIRECTORY_SEPARATOR, getcwd());
            return end($parts);
        }

        // Sandbox projects cannot have releases.
        if (strpos($gitCmd->getOutput(), 'drupal.org/sandbox')) {
            $this->stdOut->writeln("Sandbox projects cannot have releases.");
            return '';
        }

        // The URL will be in one of these formats:
        // * [username]@git.drupal.org:project/[projectname].git
        // * https://git.drupal.org/project/[projectname].git
        $path = str_replace('.git', '', $gitCmd->getOutput());
        if ($path === '') {
            $this->stdOut->writeln("The commits URL could not be discovered.");
            return '';
        }
        $path = explode('/', $path);
        return array_pop($path);
    }
}
