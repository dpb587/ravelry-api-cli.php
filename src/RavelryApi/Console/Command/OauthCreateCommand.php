<?php

namespace RavelryApi\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use RavelryApi\Helper\OauthHelper;

class OauthCreateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('oauth:create')
            ->setDescription('Create an OAuth session token.')
            ->setDefinition(
                [
                    new InputOption(
                        'scope',
                        '',
                        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                        'Optional Scope'
                    ),
                ]
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();
        $helper = new OauthHelper($client);

        $url = $helper->beginSession(
            'https://httpbin.org/get',
            $input->getOption('scope')
        );

        $prefix = (isset($_SERVER['argv'][0]) ? ($_SERVER['argv'][0] . ' ') : '');

        $output->writeln('Please visit the following URL to authorize your OAuth session...');
        $output->writeln('');
        $output->writeln('    <info>' . $url . '</info>');
        $output->writeln('');

        if ($input->isInteractive()) {
            $dialog = $this->getHelperSet()->get('dialog');

            $output->writeln('Once complete, provide the oauth_verifier you receive...');
            $output->writeln('');

            $verifier = $dialog->ask($output, '    <comment>Verifier</comment>: ');
            $output->writeln('');

            $helper->confirmSession(
                $client->getAuthentication()->getTokenStorage()->getRequestToken(),
                $verifier
            );

            $output->writeln('Session confirmed');
        } else {
            $output->writeln('Then, run the following with arguments you receive...');
            $output->writeln('');
            $output->writeln('    <info>' . $prefix . 'oauth:confirm {oauth_token} {oauth_verifier}</info>');
            $output->writeln('');
        }
    }
}
