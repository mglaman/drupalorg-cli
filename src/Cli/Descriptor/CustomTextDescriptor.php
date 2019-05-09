<?php

namespace mglaman\DrupalOrgCli\Descriptor;

use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Descriptor\TextDescriptor as BaseTextDescriptor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputDefinition;

class CustomTextDescriptor extends BaseTextDescriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeApplication(Application $application, array $options = array())
    {
        $describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
        $description = new ApplicationDescription($application, $describedNamespace);

        if (isset($options['raw_text']) && $options['raw_text']) {
            $width = $this->getColumnWidth($description->getCommands());

            foreach ($description->getCommands() as $command) {
                $this->writeText(sprintf("%-${width}s %s", $command->getName(), $command->getDescription()), $options);
                $this->writeText("\n");
            }
        } else {
            if ('' != $help = $application->getHelp()) {
                $this->writeText("$help\n\n", $options);
            }

            $this->describeInputDefinition(new InputDefinition($application->getDefinition()->getOptions()), $options);

            $this->writeText("\n");
            $this->writeText("\n");

            $width = $this->getColumnWidth($description->getCommands());

            if ($describedNamespace) {
                $this->writeText(sprintf('<comment>Available commands for the "%s" namespace:</comment>', $describedNamespace), $options);
            } else {
                $this->writeText('<comment>Available commands:</comment>', $options);
            }

            // add commands by namespace
            foreach ($description->getNamespaces() as $namespace) {
                if (!$describedNamespace && ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                    $this->writeText("\n");
                    $this->writeText(' <comment>'.$namespace['id'].'</comment>', $options);
                }

                foreach ($namespace['commands'] as $name) {
                    $command = $description->getCommand($name);
                    $aliases = $command->getAliases();
                    if ($aliases && in_array($name, $aliases)) {
                        // skip aliases
                        continue;
                    }

                    $this->writeText("\n");
                    $this->writeText(
                        sprintf(
                            "  %-${width}s %s",
                            "<info>$name</info>" . $this->formatAliases($aliases),
                            $command->getDescription()
                        ),
                        $options
                    );
                }
            }

            $this->writeText("\n");
        }
    }

    /**
     * @param array $aliases
     *
     * @return string
     */
    protected function formatAliases(array $aliases)
    {
        return $aliases ? " (" . implode(', ', $aliases) . ")" : '';
    }



    /**
     * {@inheritdoc}
     */
    private function writeText($content, array $options = array())
    {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }

    /**
     * @param \Symfony\Component\Console\Command\Command[] $commands
     *
     * @return int
     */
    protected function getColumnWidth(array $commands)
    {
        $width = 0;
        foreach ($commands as $command) {
            $aliasesString = $this->formatAliases($command->getAliases());
            $commandWidth = strlen($command->getName()) + strlen($aliasesString);
            $width = $commandWidth > $width ? $commandWidth : $width;
        }
        // Add the indent.
        $width += 2;
        // Accommodate tags.
        $width += strlen('<info></info>');
        return $width;
    }
}
