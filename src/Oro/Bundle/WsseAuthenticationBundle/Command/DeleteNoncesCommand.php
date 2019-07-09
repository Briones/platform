<?php

namespace Oro\Bundle\WsseAuthenticationBundle\Command;

use Doctrine\Common\Cache\FlushableCache;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Flushes WSSE nonce cache.
 */
class DeleteNoncesCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'oro:wsse:nonces:delete';

    /** @var ContainerInterface */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct();

        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Flushes WSSE nonce cache');
        $this->setDefinition([
            new InputOption(
                'firewall',
                null,
                InputArgument::OPTIONAL,
                'Firewall name. Default: wsse_secured',
                'wsse_secured'
            ),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $firewall = $input->getOption('firewall');

        $this->getNonceCache($firewall)->flushAll();

        $output->writeln(
            sprintf(
                'Deleted nonce cache for <comment>%s</comment> firewall.',
                $firewall
            )
        );
    }

    /**
     * @param string $firewallName
     *
     * @return FlushableCache
     */
    private function getNonceCache(string $firewallName): FlushableCache
    {
        $serviceId = 'oro_wsse_authentication.nonce_cache.' . $firewallName;
        if (!$this->container->has($serviceId)) {
            throw new \InvalidArgumentException(
                sprintf('WSSE nonce cache for firewall "%s" is not defined', $firewallName)
            );
        }

        return $this->container->get($serviceId);
    }
}
