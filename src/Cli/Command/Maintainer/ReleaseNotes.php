<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\CommitParser;
use mglaman\DrupalOrg\DrupalOrg;
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
     * @var array<int, string>
     */
    private const CATEGORY_MAP = [
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
            '--format=%x00%s%x1f%ae%x1f%ce%x1f%b',
            "$ref1..$ref2",
        ]);
        if ($gitLog->getExitCode() !== 0) {
            var_export($gitLog->getErrorOutput());
            $this->stdOut->writeln('Error getting commit log');
            return 1;
        }

        $format = $this->stdIn->getOption('format');

        // Parse commits into structured objects.
        $commits = [];
        $commitBlocks = array_filter(explode("\x00", $gitLog->getOutput()));
        foreach ($commitBlocks as $block) {
            $parts = explode("\x1f", $block, 4);
            $commit = new \stdClass();
            $commit->title = trim($parts[0]);
            $commit->author_email = trim($parts[1] ?? '');
            $commit->committer_email = trim($parts[2] ?? '');
            $commit->message = $parts[3] ?? '';
            $commits[] = $commit;
        }

        // Extract all NIDs from commit titles.
        $nids = [];
        foreach ($commits as $commit) {
            $nid = CommitParser::getNid($commit->title);
            if ($nid !== null && !isset($nids[$nid])) {
                $nids[$nid] = $nid;
            }
        }

        // Work out what the project name is.
        $project = trim($this->getProjectName());
        if ($project === '') {
            return 1;
        }

        // Fetch data from Drupal.org concurrently.
        $drupalOrg = new DrupalOrg($this->client->getGuzzleClient());
        $nidList = array_values($nids);
        $contributorsFromApi = $drupalOrg->getContributorsFromJsonApi($nidList);
        $issueDetails = $drupalOrg->getIssueDetails($nidList);
        $projectId = $drupalOrg->getProjectId($project);

        // Track all contributors across commits.
        $users = [];

        // Process commits into categorized changes.
        $processedChanges = [];
        foreach ($commits as $commit) {
            $nid = CommitParser::getNid($commit->title);

            // Determine issue category.
            $issueCategoryLabel = 'Misc';
            if ($nid !== null && isset($issueDetails[$nid])) {
                $issueCategory = $issueDetails[$nid]->fieldIssueCategory;
                $issueCategoryLabel = self::CATEGORY_MAP[$issueCategory] ?? 'Misc';
            }

            // Gather contributors: JSON:API first, then commit parsing, then email fallback.
            $commitContributors = [];
            if ($nid !== null && isset($contributorsFromApi[$nid]) && $contributorsFromApi[$nid] !== []) {
                $commitContributors = $contributorsFromApi[$nid];
            } else {
                $commitContributors = CommitParser::extractUsernames($commit);
                if ($commitContributors === []) {
                    // Fallback: extract username from noreply email.
                    foreach ([$commit->author_email, $commit->committer_email] as $email) {
                        if (preg_match('/(?<=[0-9]-)([a-zA-Z0-9\-_\.]{2,255})(?=@users\.noreply\.drupalcode\.org)/', $email, $m)) {
                            $commitContributors[] = $m[1];
                        }
                    }
                    $commitContributors = array_values(array_unique($commitContributors));
                }
            }

            foreach ($commitContributors as $username) {
                if (!isset($users[$username])) {
                    $users[$username] = 1;
                } else {
                    $users[$username]++;
                }
            }

            if ($nid !== null && !isset($processedChanges[$issueCategoryLabel][$nid])) {
                $processedChanges[$issueCategoryLabel][$nid] = $this->formatLine($commit->title, $format);
            }
        }
        ksort($processedChanges);

        // Fetch change records if we have a project ID.
        $changeRecords = [];
        if ($projectId !== null) {
            $changeRecords = $drupalOrg->getChangeRecords($projectId, $ref2);
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
                    sprintf('### Contributors (%s)', count($users))
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    implode(
                        ', ',
                        array_map(
                            function ($username) use ($format): string {
                                return $this->formatUsername($username, $format);
                            },
                            array_keys($users)
                        )
                    )
                );
                $this->stdOut->writeln('');
                $this->stdOut->writeln('### Changelog');
                $this->stdOut->writeln('');
                $this->stdOut->writeln(
                    sprintf(
                        '**Issues**: %s issues resolved.',
                        count($nids)
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
                if ($changeRecords !== []) {
                    $this->stdOut->writeln('### Change Records');
                    $this->stdOut->writeln('');
                    foreach ($changeRecords as $record) {
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
                    sprintf('<h3>Contributors (%s)</h3>', count($users))
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
                                array_keys($users)
                            )
                        )
                    )
                );
                $this->stdOut->writeln('<h3>Changelog</h3>');
                $this->stdOut->writeln(
                    sprintf(
                        '<p><strong>Issues:</strong> %s issues resolved.</p>',
                        count($nids)
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

                if ($changeRecords !== []) {
                    $this->stdOut->writeln('<h3>Change Records</h3>');
                    $this->stdOut->writeln('<ul>');
                    foreach ($changeRecords as $record) {
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

        // Strip any trailing "by username:" attribution from the line (now tracked separately).
        $value = preg_replace('/\s+by [^:]+:.*$/S', '', $value);

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
