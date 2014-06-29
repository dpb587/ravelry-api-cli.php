<?php

namespace RavelryApi\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use GuzzleHttp\Subscriber\Log\Formatter;
use RavelryApi\Authentication\BasicAuthentication;
use RavelryApi\Authentication\OauthAuthentication;
use RavelryApi\Authentication\OauthTokenStorage\FileTokenStorage;
use RavelryApi\Client;
use RavelryApi\Subscriber\RavelryDebugSubscriber;
use RuntimeException;

class Application extends BaseApplication
{
    protected $client;
    protected $input;

    public function __construct()
    {
        parent::__construct('ravelry-api', Client::VERSION . '/' . Manifest::getVersion());
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new Command\OauthCreateCommand();
        $commands[] = new Command\OauthConfirmCommand();

        $schema = Client::loadServiceDescription();

        foreach ($schema['operations'] as $operationName => $operation) {
            $commands[] = new Command\SchemaOperationCommand(
                $operation['_cliname'],
                $operation,
                $operationName
            );
        }

        return $commands;
    }

    protected function configureIO(InputInterface $input, OutputInterface $output)
    {
        parent::configureIO($input, $output);

        // save this for when we need to build an API client
        $this->input = $input;
        $this->output = $output;
    }

    public function getClient()
    {
        if (null === $this->client) {
            $accessKey = $this->input->getOption('auth-access-key') ?: getenv('RAVELRY_ACCESS_KEY');
            $secretKey = $this->input->getOption('auth-secret-key') ?: getenv('RAVELRY_SECRET_KEY');
            $personalKey = $this->input->getOption('auth-personal-key') ?: getenv('RAVELRY_PERSONAL_KEY');
            $tokenStorage = $this->input->getOption('oauth-token-storage') ?: (getenv('HOME') . '/.ravelryapi');

            if ((!empty($accessKey)) && (!empty($personalKey))) {
                $auth = new BasicAuthentication($accessKey, $personalKey);
            } elseif ((!empty($accessKey)) && (!empty($secretKey))) {
                $auth = new OauthAuthentication(
                    new FileTokenStorage($tokenStorage),
                    $accessKey,
                    $secretKey
                );
            } else {
                throw new RuntimeException('Ravelry API keys are missing.');
            }

            $config = [];

            if (null !== $mock = $this->input->getOption('debug-mock')) {
                $config['adapter'] = new \GuzzleHttp\Adapter\MockAdapter(
                    (new \GuzzleHttp\Message\MessageFactory())->fromMessage(
                        file_get_contents($mock)
                    )
                );
            }

            $this->client = new \RavelryApi\Client($auth, $config);

            $guzzle = $this->client->getGuzzle();

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $this->output->getVerbosity()) {
                $guzzle->getEmitter()->attach(new LogSubscriber(null, Formatter::DEBUG));
            }

            if (null !== $log = $this->input->getOption('debug-log')) {
                $guzzle->getEmitter()->attach(
                    new LogSubscriber(
                        fopen($this->input->getOption('debug-log'), 'a'),
                        "========\ntimestamp: {ts}\nlocalhost: {hostname}\n>>>>>>>>\n{request}\n<<<<<<<<\n{response}\n--------\n{error}\n========\n\n"
                    )
                );
            }
        }

        return $this->client;
    }

    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(
            new InputOption(
                '--auth-access-key',
                null,
                InputOption::VALUE_REQUIRED,
                'API Access Key [default "$RAVELRY_ACCESS_KEY"]'
            )
        );

        $definition->addOption(
            new InputOption(
                '--auth-secret-key',
                null,
                InputOption::VALUE_REQUIRED,
                'API Secret Key [default "$RAVELRY_SECRET_KEY"]'
            )
        );

        $definition->addOption(
            new InputOption(
                '--auth-personal-key',
                null,
                InputOption::VALUE_REQUIRED,
                'API Personal Key [default "$RAVELRY_PERSONAL_KEY"]'
            )
        );

        $definition->addOption(
            new InputOption(
                '--oauth-token-storage',
                null,
                InputOption::VALUE_REQUIRED,
                'Storage path for OAuth tokens [default "$HOME/.ravelryapi"]'
            )
        );

        $definition->addOption(
            new InputOption(
                '--debug-mock',
                null,
                InputOption::VALUE_REQUIRED,
                'Use the passed file to create a mock response'
            )
        );

        $definition->addOption(
            new InputOption(
                '--debug-log',
                null,
                InputOption::VALUE_REQUIRED,
                'Verbosely log all details of the HTTP requests and responses to a file'
            )
        );

        return $definition;
    }
}
