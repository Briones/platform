<?php

namespace Oro\Bundle\ApiBundle\Processor\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\ApiBundle\Processor\Context;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\SoapBundle\Handler\DeleteHandler;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for processors that deletes entities by DeleteHandler.
 */
abstract class DeleteDataByDeleteHandler implements ProcessorInterface
{
    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var ContainerInterface */
    protected $container;

    /**
     * @param DoctrineHelper     $doctrineHelper
     * @param ContainerInterface $container
     */
    public function __construct(DoctrineHelper $doctrineHelper, ContainerInterface $container)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var Context $context */

        if (!$context->hasResult()) {
            // result deleted or not supported
            return;
        }

        $entityClass = $context->getClassName();
        if (!$this->doctrineHelper->isManageableEntityClass($entityClass)) {
            return;
        }

        $deleteHandlerServiceId = $context->getConfig()->getDeleteHandler();
        if (!$deleteHandlerServiceId) {
            $deleteHandlerServiceId = $this->getDefaultDeleteHandler();
        }

        $deleteHandler = $this->container->get($deleteHandlerServiceId);
        if ($deleteHandler instanceof DeleteHandler) {
            $this->processDelete(
                $context->getResult(),
                $deleteHandler,
                $this->doctrineHelper->getEntityManagerForClass($entityClass)
            );
            $context->removeResult();
        }
    }

    /**
     * @return string
     */
    protected function getDefaultDeleteHandler()
    {
        return 'oro_soap.handler.delete';
    }

    /**
     * Deletes entity(es) stored in the given result property of the Context using the delete handler
     *
     * @param mixed                  $data
     * @param DeleteHandler          $handler
     * @param EntityManagerInterface $em
     */
    abstract protected function processDelete($data, DeleteHandler $handler, EntityManagerInterface $em);
}
