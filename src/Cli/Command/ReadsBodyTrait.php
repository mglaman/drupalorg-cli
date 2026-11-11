<?php

namespace mglaman\DrupalOrgCli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * Reads a Markdown body from --message or --file (with - for stdin).
 */
trait ReadsBodyTrait
{
    /**
     * @param string $noun
     *   What the body is, for error messages, e.g. "comment body".
     * @param bool $required
     *   Whether one of --message or --file must be passed.
     */
    private function readBody(InputInterface $input, string $noun, bool $required = true): string
    {
        $message = $input->getOption('message');
        $file = $input->getOption('file');
        if ($message !== null && $file !== null) {
            throw new \InvalidArgumentException('Pass exactly one of --message or --file.');
        }
        if ($message === null && $file === null) {
            if ($required) {
                throw new \InvalidArgumentException('Pass exactly one of --message or --file.');
            }
            return '';
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
            throw new \InvalidArgumentException(sprintf('Could not read the %s from %s.', $noun, $file));
        }
        return $contents;
    }
}
