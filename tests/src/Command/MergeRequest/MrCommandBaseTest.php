<?php

namespace mglaman\DrupalOrg\Tests\Command\MergeRequest;

use mglaman\DrupalOrgCli\Command\MergeRequest\MrCommandBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(MrCommandBase::class)]
class MrCommandBaseTest extends TestCase
{
    public function testClassExists(): void
    {
        $command = new class ('test:mr-command') extends MrCommandBase {
            protected function configure(): void
            {
                $this->configureNidAndMrIid();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        self::assertInstanceOf(MrCommandBase::class, $command);
    }

    public function testConfigureNidAndMrIidAddsArguments(): void
    {
        $command = new class ('test:mr-command') extends MrCommandBase {
            protected function configure(): void
            {
                $this->configureNidAndMrIid();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $definition = $command->getDefinition();
        self::assertTrue($definition->hasArgument('nid'));
        self::assertTrue($definition->hasArgument('mr-iid'));
        self::assertFalse($definition->getArgument('nid')->isRequired());
        self::assertFalse($definition->getArgument('mr-iid')->isRequired());
    }
}
