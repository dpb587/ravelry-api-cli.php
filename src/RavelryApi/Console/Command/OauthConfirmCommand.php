<?php

namespace RavelryApi\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use RavelryApi\Helper\OauthHelper;

class OauthConfirmCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('oauth:confirm')
            ->setDescription('Confirm an OAuth session with the token and verifier from oauth:begin.')
            ->setDefinition(
                [
                    new InputArgument('token', InputArgument::REQUIRED, 'OAuth Token'),
                    new InputArgument('verifier', InputArgument::REQUIRED, 'OAuth Verifier'),
                ]
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();
        $helper = new OauthHelper($client);

        $helper->confirmSession(
            $input->getArgument('token'),
            $input->getArgument('verifier')
        );

        $output->writeln('Session confirmed');
    }
}
