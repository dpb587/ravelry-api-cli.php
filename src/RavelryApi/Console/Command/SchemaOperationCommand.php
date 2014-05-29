<?php

namespace RavelryApi\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Yaml;

class SchemaOperationCommand extends Command
{
    protected $operation;
    protected $operationName;

    public function __construct($name, array $operation, $operationName)
    {
        $this->operation = $operation;
        $this->operationName = $operationName;

        parent::__construct(str_replace('_', ':', $name));
    }

    private function delveDefinitionProperties(array $properties, $context = '')
    {
        $definition = [];

        foreach ($properties as $parameterName => $parameter) {
            if (('' == $context) && (in_array($parameterName, [ 'debug', 'extras' ]))) {
                $definition[] = new InputOption(
                    $parameterName,
                    null,
                    InputOption::VALUE_NONE,
                    isset($parameter['description']) ? $parameter['description'] : null
                );
            } elseif (!empty($parameter['static'])) {
                continue;
            } elseif ('object' == $parameter['type']) {
                $definition = array_merge(
                    $definition,
                    $this->delveDefinitionProperties($parameter['properties'], $parameterName . ':')
                );
            } else {
                $suffix = [];

                if (!empty($parameter['required'])) {
                    $suffix[] = 'required';
                }

                if ((!empty($parameter['location'])) && ('postFile' == $parameter['location'])) {
                    $suffix[] = 'file path';
                }

                $definition[] = new InputOption(
                    $context . str_replace('_', '-', $parameterName),
                    null,
                    InputOption::VALUE_REQUIRED,
                    isset($parameter['description']) ? ($parameter['description'] . ($suffix ? (' [' . implode(', ', $suffix) . ']') : '')) : implode(', ', $suffix)
                );
            }
        }

        return $definition;
    }

    private function delveInputProperties(InputInterface $input, array $properties, array &$parsed, $context = '')
    {
        foreach ($properties as $parameterName => $parameter) {
            if (('' == $context) && (in_array($parameterName, [ 'debug', 'extras' ]))) {
                if ($input->getOption($parameterName)) {
                    $parsed[$parameterName] = true;
                }
            } elseif (!empty($parameter['static'])) {
                continue;
            } elseif ('object' == $parameter['type']) {
                $parsed[$parameterName] = [];

                $this->delveInputProperties(
                    $input,
                    $parameter['properties'],
                    $parsed[$parameterName],
                    $context . $parameterName . ':'
                );
            } else {
                $value = $input->getOption(str_replace('_', '-', $context . $parameterName));

                if (null !== $value) {
                    if ('integer' == $parameter['type']) {
                        $value = (int) $value;
                    } elseif ('boolean' == $parameter['type']) {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }

                    $parsed[$parameterName] = $value;
                } elseif ($parameter['required']) {
                    throw new \RuntimeException('The "--' . $context . $parameterName . '" option is required.');
                }
            }
        }
    }

    protected function configure()
    {
        $definition = [];

        if (isset($this->operation['parameters'])) {
            $definition = array_merge(
                $definition,
                $this->delveDefinitionProperties($this->operation['parameters'])
            );
        }

        $this->setDefinition($definition);

        if (isset($this->operation['description'])) {
            $this->setDescription($this->operation['description']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = [];

        $this->delveInputProperties($input, $this->operation['parameters'], $args);

        $result = call_user_func(
            [
                $this->getApplication()->getClient(),
                $this->operationName
            ],
            $args
        );


        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $serialize = [
                'status_code' => $result->getStatusCode(),
                'status_text' => $result->getStatusText(),
                'etag' => $result->getEtag(),
                'data' => $result->toArray(),
            ];
        } else {
            $serialize = $result->toArray();
        }

        $output->writeln(
            json_encode(
                $serialize,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
