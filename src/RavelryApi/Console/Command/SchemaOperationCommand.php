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

    private function getInputDescription(array $parameter, $suffix = [])
    {
        if (!empty($parameter['required'])) {
            $suffix[] = 'required';
        }

        if ((!empty($parameter['location'])) && ('postFile' == $parameter['location'])) {
            $suffix[] = 'file path';
        }

        if (isset($parameter['enum'])) {
            $suffix[] = 'allowed values: ' . implode(', ', $parameter['enum']);
        }

        return isset($parameter['description'])
            ? ($parameter['description'] . ($suffix ? (' [' . implode('; ', $suffix) . ']') : ''))
            : implode('; ', $suffix)
            ;
    }

    private function delveDefinitionProperties(array $properties, $context = '')
    {
        $definition = [];

        foreach ($properties as $parameterName => $parameter) {
            $cliName = $context . str_replace('_', '-', $parameterName);
            $parameterType = isset($parameter['_clitype']) ? $parameter['_clitype'] : (isset($parameter['type']) ? $parameter['type'] : null);

            if (('' == $context) && (in_array($parameterName, [ 'debug', 'extras' ]))) {
                $definition[$cliName] = new InputOption(
                    $cliName,
                    null,
                    InputOption::VALUE_NONE,
                    $this->getInputDescription($parameter)
                );
            } elseif (!empty($parameter['static'])) {
                continue;
            } elseif ('object' == $parameterType) {
                $definition = array_merge(
                    $definition,
                    $this->delveDefinitionProperties($parameter['properties'], $parameterName . ':')
                );
            } elseif ('array' == $parameterType) {
                $definition[$cliName] = new InputOption(
                    $cliName,
                    null,
                    InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                    $this->getInputDescription($parameter)
                );
            } else {
                $definition[$cliName] = new InputOption(
                    $cliName,
                    null,
                    InputOption::VALUE_REQUIRED,
                    $this->getInputDescription($parameter)
                );
            }
        }

        return $definition;
    }

    private function delveInputProperties(InputInterface $input, array $properties, array &$parsed, $context = '')
    {
        foreach ($properties as $parameterName => $parameter) {
            $parameterType = isset($parameter['_clitype']) ? $parameter['_clitype'] : (isset($parameter['type']) ? $parameter['type'] : null);

            if (('' == $context) && (in_array($parameterName, [ 'debug', 'extras' ]))) {
                if ($input->getOption($parameterName)) {
                    $parsed[$parameterName] = true;
                }
            } elseif (!empty($parameter['static'])) {
                continue;
            } elseif ('object' == $parameterType) {
                $parsed[$parameterName] = [];

                $this->delveInputProperties(
                    $input,
                    $parameter['properties'],
                    $parsed[$parameterName],
                    $context . $parameterName . ':'
                );
            } elseif ('array' == $parameterType) {
                $parsed[$parameterName] = $input->getOption(str_replace('_', '-', $context . $parameterName));

                if (isset($parameter['enum'])) {
                    foreach ($parsed[$parameterName] as $enumCheck) {
                        if (!in_array($enumCheck, $parameter['enum'])) {
                            throw new \UnexpectedValueException('Parameter ' . $parameterName . ' only allows: ' . implode(', ', $parameter['enum']));
                        }
                    }
                }
            } else {
                $value = $input->getOption(str_replace('_', '-', $context . $parameterName));

                if (('postFile' == $parameterType) && ('-' == $value)) {
                    $value = 'php://stdin';
                }

                if (is_array($value)) {
                    // this assumes the only possible way to have an array is through the additionalParameters method
                    foreach ($value as $value2) {
                        list($vn, $vk) = explode('=', $value2, 2);

                        $parsed[$vn] = $vk;
                    }
                } elseif (null !== $value) {
                    if ('integer' == $parameterType) {
                        $value = (int) $value;
                    } elseif ('boolean' == $parameterType) {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }

                    $parsed[$parameterName] = $value;
                } elseif (!empty($parameter['required'])) {
                    throw new \RuntimeException('The "--' . $context . $parameterName . '" option is required.');
                }
            }
        }
    }

    protected function configure()
    {
        $definition = [];

        if (isset($this->operation['parameters'])) {
            $definition = $this->delveDefinitionProperties($this->operation['parameters']);
        }

        if (isset($this->operation['additionalParameters'])) {
            $definition[$this->operation['additionalParameters']['_cliname']] = new InputOption(
                $this->operation['additionalParameters']['_cliname'],
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                $this->getInputDescription($this->operation['additionalParameters'], [ 'KEY=VALUE' ])
            );
        }

        $laterArgs = [
            'debug' => true,
            'extras' => true,
            'etag' => true,
        ];

        $this->setDefinition(
            array_values(
                array_merge(
                    array_diff_key($definition, $laterArgs),
                    array_intersect_key($definition, $laterArgs)
                )
            )
        );

        if (isset($this->operation['description'])) {
            $this->setDescription($this->operation['description']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = [];

        $this->delveInputProperties($input, $this->operation['parameters'], $args);

        if (isset($this->operation['additionalParameters'])) {
            $this->delveInputProperties(
                $input,
                [
                    $this->operation['additionalParameters']['_cliname'] => $this->operation['additionalParameters'],
                ],
                $args
            );
        }

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
