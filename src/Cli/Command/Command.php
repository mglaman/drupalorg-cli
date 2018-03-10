<?php
namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrgCli\Cache;
use mglaman\DrupalOrg\Client;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

abstract class Command extends BaseCommand
{
    /** @var OutputInterface|null */
    protected $stdOut;
    /** @var OutputInterface|null */
    protected $stdErr;
    /** @var  InputInterface|null */
    protected $stdIn;
    /** @var bool */
    protected static $interactive = false;
    /**
     * @var \mglaman\DrupalOrg\Client
     */
    protected $client;

    public function __construct($name = null)
    {
        parent::__construct($name);

        Cache::setCacheDir(CLI_ROOT . '/cache');
    }

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

    protected function debug($message) {
        if ($this->stdOut->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->stdOut->writeln('<comment>' . $message . '</comment>');
        }
    }

    /**
     * Gets a cache value.
     *
     * @param $cid
     * @return false|mixed
     */
    protected function getCacheKey($cid) {
        return Cache::getCache()->fetch($cid);
    }

    /**
     * Sets a cache value.
     *
     * @param $cid
     * @param $value
     * @param int $ttl
     * @return bool
     */
    protected function setCacheKey($cid, $value, $ttl = 3600) {
        return Cache::getCache()->save($cid, $value, $ttl);
    }

    /**
     * Wrapper method to retrieve a node, using cache.
     *
     * @param $nid
     * @param bool $reset
     * @return false|\mglaman\DrupalOrg\RawResponse|mixed
     */
    protected function getNode($nid, $reset = false) {
        $cid = implode(':', ['node', $nid]);
        $cached = $this->getCacheKey($cid);
        if ($cached === FALSE || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached = $this->client->getNode($nid);
            $this->setCacheKey($cid, $cached, 600);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached;
    }

    /**
     * Wrapper method to retrieve a project, using cache.
     *
     * @param $nid
     * @param bool $reset
     * @return \mglaman\DrupalOrg\Response
     */
    protected function getProject($machineName, $reset = false) {
        $cid = implode(':', ['project', $machineName]);
        $cached = $this->getCacheKey($cid);
        if ($cached === FALSE || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached = $this->client->getProject($machineName);
            $this->setCacheKey($cid, $cached, 21600);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached;
    }

    /**
     * Wrapper method to retrieve a file, using cache.
     *
     * Files don't change, so we cache 'em for good.
     *
     * @param $fid
     * @param bool $reset
     * @return false|\mglaman\DrupalOrg\RawResponse|mixed
     */
    protected function getFile($fid, $reset = false) {
        $cid = implode(':', ['file', $fid]);
        $cached = $this->getCacheKey($cid);
        if ($cached === FALSE || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached = $this->client->getFile($fid);
            $this->setCacheKey($cid, $cached, 0);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached;
    }

    /**
     * Wrapper method to retrieve a PIFT job, using cache.
     *
     * We only cache a PIFT job is the status is "completed"
     *
     * @param $jobId
     * @param bool $reset
     * @return false|\mglaman\DrupalOrg\RawResponse|mixed
     */
    protected function getPiftJob($jobId, $reset = false) {
        $cid = implode(':', ['pift', $jobId]);
        $cached = $this->getCacheKey($cid);
        if ($cached === FALSE || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached = $this->client->getPiftJob($jobId);
            $this->debug($cached->get('status'));
            if ($cached->get('status') == 'complete') {
                $this->setCacheKey($cid, $cached, 60);
            }
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached;
    }

    /**
     * Wrapper method to get PIFT jobs, using cache.
     *
     * @param array $options
     * @param bool $reset
     * @return false|\mglaman\DrupalOrg\RawResponse|mixed
     */
    protected function getPiftJobs(array $options, $reset = false) {
        $cid = implode(':', ['pift:jobs:', implode(':', $options)]);
        $cached = $this->getCacheKey($cid);
        if ($cached === FALSE || $reset) {
            $this->debug("Cache MISS for $cid");
            $cached = $this->client->getPiftJobs($options);
            $this->setCacheKey($cid, $cached, 60);
        } else {
            $this->debug("Cache HIT for $cid");
        }
        return $cached;
    }
}
