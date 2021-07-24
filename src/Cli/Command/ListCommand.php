<?php

namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrgCli\Descriptor\CustomTextDescriptor;
use Symfony\Component\Console\Command\ListCommand as BaseListCommand;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseListCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new DescriptorHelper();
        $helper->register('txt', new CustomTextDescriptor());
        $helper->describe(
            $output,
            $this->getApplication(),
            array(
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
            'namespace' => $input->getArgument('namespace'),
            )
        );
        return 0;
    }
}
