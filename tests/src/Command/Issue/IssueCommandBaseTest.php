<?php

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(IssueCommandBase::class)]
class IssueCommandBaseTest extends TestCase
{
    public function testClassExists(): void
    {
        // Verify the abstract class can be extended without the removed business-logic methods.
        $command = new class ('test:command') extends IssueCommandBase {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        self::assertInstanceOf(IssueCommandBase::class, $command);
    }
}
