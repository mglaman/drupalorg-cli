<?php
namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrg\Response;
use mglaman\DrupalOrgCli\Cache;
use mglaman\DrupalOrg\Client;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Process\Process;

abstract class Command extends BaseCommand
{
    /** @var OutputInterface|null */
    protected ?OutputInterface $stdOut;
    /** @var OutputInterface|null */
    protected ?OutputInterface $stdErr;
    /** @var  InputInterface|null */
    protected ?InputInterface $stdIn;
    /** @var bool */
    protected static bool $interactive = false;
    /**
     * @var \mglaman\DrupalOrg\Client
     */
    protected Client $client;

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->stdOut = $output;
        $this->stdErr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $this->stdIn = $input;
        self::$interactive = $input->isInteractive();
        $this->client = new Client();
    }

    protected function debug($message)
    {
        if ($this->stdOut->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->stdOut->writeln('<comment>' . $message . '</comment>');
        }
    }

    protected function getCacheItem(string $cid): CacheItemInterface
    {
        return Cache::getCache()->getItem($cid);
    }

    protected function getNode(string $nid, bool $reset = false): RawResponse
    {
        $cid = implode('--', ['node', $nid]);
        $cached = $this->getCacheItem($cid);
        if (!$cached->isHit() || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached->set($this->client->getNode($nid));
            $cached->expiresAfter(600);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached->get();
    }

    protected function getProject(string $machineName, bool $reset = false): Response {
        $cid = implode('--', ['project', $machineName]);
        $cached = $this->getCacheItem($cid);
        if (!$cached->isHit() || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached->set($this->client->getProject($machineName));
            $cached->expiresAfter(21600);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached->get();
    }

    protected function getFile(string $fid, bool $reset = false): RawResponse
    {
        $cid = implode('--', ['file', $fid]);
        $cached = $this->getCacheItem($cid);
        if (!$cached->isHit() || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached->set($this->client->getFile($fid));
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached->get();
    }

    protected function getPiftJob(string $jobId, bool $reset = false): RawResponse
    {
        $cid = implode('--', ['pift', $jobId]);
        $cached = $this->getCacheItem($cid);
        if (!$cached->isHit() || $reset) {
            $this->debug("Cache MISS for $cid");
            $data = $this->client->getPiftJob($jobId);
            $this->debug($data->get('status'));
            if ($data->get('status') === 'complete') {
                $cached->set($data);
                $cached->expiresAfter(60);
            }
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached->get();
    }

    /**
     * Wrapper method to get PIFT jobs, using cache.
     *
     * @param array $options
     * @param bool $reset
     *
     * @return false|\mglaman\DrupalOrg\RawResponse|mixed
     */
    protected function getPiftJobs(array $options, bool $reset = false)
    {
        $cid = implode('--', ['pift:jobs:', implode(':', $options)]);
        $cached = $this->getCacheItem($cid);
        if (!$cached->isHit() || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached->set($this->client->getPiftJobs($options));
            $cached->expiresAfter(60);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached->get();
    }

    protected function runProcess($cmd): Process {
        $process = new Process($cmd);
        $process->run();
        return $process;
    }
}
