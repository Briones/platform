<?php

namespace Oro\Bundle\EntityConfigBundle\EventListener;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\ImportExportBundle\Event\StrategyEvent;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * This listener prevents to change a simple field config to be an attribute, during import.
 */
class ImportStrategyListener
{
    /** @var TranslatorInterface */
    private $translator;

    /** @var ImportStrategyHelper */
    private $strategyHelper;

    /** @var ConfigManager */
    private $configManager;

    /**
     * @param TranslatorInterface $translator
     * @param ImportStrategyHelper $strategyHelper
     */
    public function __construct(
        TranslatorInterface $translator,
        ImportStrategyHelper $strategyHelper
    ) {
        $this->translator = $translator;
        $this->strategyHelper = $strategyHelper;
    }

    /**
     * @param ConfigManager $configManager
     */
    public function setConfigManager(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * @param StrategyEvent $event
     */
    public function onProcessAfter(StrategyEvent $event)
    {
        $context = $event->getContext();
        $entity = $event->getEntity();
        if (!$entity instanceof FieldConfigModel) {
            return;
        }

        $existingEntity = $context->getValue('existingEntity');
        if (!$existingEntity) {
            return;
        }

        $attributeConfig = $this->configManager->createFieldConfigByModel($entity, 'attribute');
        if (!$attributeConfig->is('is_attribute')) {
            return;
        }

        $existingAttributeConfig = $this->configManager->createFieldConfigByModel($existingEntity, 'attribute');
        if ($existingAttributeConfig->is('is_attribute')) {
            return;
        }

        $error = $this->translator->trans('oro.entity_config.import.message.cant_replace_extend_field');
        $context->incrementErrorEntriesCount();
        $this->strategyHelper->addValidationErrors([$error], $context);
        $event->setEntity(null);
    }
}
