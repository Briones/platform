<?php


namespace Oro\Bundle\EntityExtendBundle\Form\Extension;

use Oro\Bundle\FormBundle\Form\Extension\Traits\FormExtendedTypeTrait;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Adds extended fields options to a form based on the configuration for an entity/field.
 */
class DynamicFieldsOptionsExtension extends AbstractTypeExtension
{
    use FormExtendedTypeTrait;

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('dynamic_fields_disabled', false);
    }
}
