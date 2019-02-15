<?php

namespace Oro\Bundle\LayoutBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Configures layout theme configuration extensions, layout DIC extension and layout renderers.
 */
class ConfigurationPass implements CompilerPassInterface
{
    private const LAYOUT_FACTORY_BUILDER_SERVICE = 'oro_layout.layout_factory_builder';
    private const PHP_RENDERER_SERVICE = 'oro_layout.php.layout_renderer';
    private const TWIG_RENDERER_SERVICE = 'oro_layout.twig.layout_renderer';
    private const LAYOUT_EXTENSION_SERVICE = 'oro_layout.extension';
    private const BLOCK_TYPE_TAG_NAME = 'layout.block_type';
    private const BLOCK_TYPE_EXTENSION_TAG_NAME = 'layout.block_type_extension';
    private const LAYOUT_UPDATE_TAG_NAME = 'layout.layout_update';
    private const CONTEXT_CONFIGURATOR_TAG_NAME = 'layout.context_configurator';
    private const DATA_PROVIDER_TAG_NAME = 'layout.data_provider';
    private const THEME_CONFIG_SERVICE = 'oro_layout.theme_extension.configuration';
    private const THEME_CONFIG_EXTENSION_TAG_NAME = 'layout.theme_config_extension';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->registerRenderers($container);
        $this->registerThemeConfigExtensions($container);
        $this->configureLayoutExtension($container);
    }

    /**
     * @param ContainerBuilder $container
     */
    private function registerRenderers(ContainerBuilder $container)
    {
        $factoryBuilderDef = $container->getDefinition(self::LAYOUT_FACTORY_BUILDER_SERVICE);
        if ($container->hasDefinition(self::PHP_RENDERER_SERVICE)) {
            $factoryBuilderDef->addMethodCall(
                'addRenderer',
                ['php', new Reference(self::PHP_RENDERER_SERVICE)]
            );
        }
        if ($container->hasDefinition(self::TWIG_RENDERER_SERVICE)) {
            $factoryBuilderDef->addMethodCall(
                'addRenderer',
                ['twig', new Reference(self::TWIG_RENDERER_SERVICE)]
            );
        }
    }

    /**
     * @param ContainerBuilder $container
     */
    private function registerThemeConfigExtensions(ContainerBuilder $container)
    {
        $themeConfigurationDef = $container->getDefinition(self::THEME_CONFIG_SERVICE);
        foreach ($container->findTaggedServiceIds(self::THEME_CONFIG_EXTENSION_TAG_NAME) as $id => $attributes) {
            $themeConfigurationDef->addMethodCall('addExtension', [new Reference($id)]);
        }
    }

    /**
     * Registers block types, block type extensions and layout updates
     *
     * @param ContainerBuilder $container
     */
    private function configureLayoutExtension(ContainerBuilder $container)
    {
        $extensionDef = $container->getDefinition(self::LAYOUT_EXTENSION_SERVICE);
        $extensionDef->replaceArgument(1, $this->getBlockTypes($container));
        $extensionDef->replaceArgument(2, $this->getBlockTypeExtensions($container));
        $extensionDef->replaceArgument(3, $this->getLayoutUpdates($container));
        $extensionDef->replaceArgument(4, $this->getContextConfigurators($container));
        $extensionDef->replaceArgument(5, $this->getDataProviders($container));
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getBlockTypes(ContainerBuilder $container)
    {
        $types = [];
        foreach ($container->findTaggedServiceIds(self::BLOCK_TYPE_TAG_NAME) as $serviceId => $tag) {
            if (empty($tag[0]['alias'])) {
                throw new InvalidConfigurationException(
                    sprintf('Tag attribute "alias" is required for "%s" service.', $serviceId)
                );
            }

            $alias = $tag[0]['alias'];
            $types[$alias] = $serviceId;
        }

        return $types;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getBlockTypeExtensions(ContainerBuilder $container)
    {
        $typeExtensions = [];
        foreach ($container->findTaggedServiceIds(self::BLOCK_TYPE_EXTENSION_TAG_NAME) as $serviceId => $tag) {
            if (empty($tag[0]['alias'])) {
                throw new InvalidConfigurationException(
                    sprintf('Tag attribute "alias" is required for "%s" service.', $serviceId)
                );
            }

            $alias = $tag[0]['alias'];
            $priority = $tag[0]['priority'] ?? 0;

            $typeExtensions[$alias][$priority][] = $serviceId;
        }
        foreach ($typeExtensions as $key => $items) {
            ksort($items);
            $typeExtensions[$key] = array_merge(...$items);
        }

        return $typeExtensions;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getLayoutUpdates(ContainerBuilder $container)
    {
        $layoutUpdates = [];
        foreach ($container->findTaggedServiceIds(self::LAYOUT_UPDATE_TAG_NAME) as $serviceId => $tag) {
            if (empty($tag[0]['id'])) {
                throw new InvalidConfigurationException(
                    sprintf('Tag attribute "id" is required for "%s" service.', $serviceId)
                );
            }

            $id = $tag[0]['id'];
            $priority = $tag[0]['priority'] ?? 0;

            $layoutUpdates[$id][$priority][] = $serviceId;
        }
        foreach ($layoutUpdates as $key => $items) {
            ksort($items);
            $layoutUpdates[$key] = array_merge(...$items);
        }

        return $layoutUpdates;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getContextConfigurators(ContainerBuilder $container)
    {
        $configurators = [];
        foreach ($container->findTaggedServiceIds(self::CONTEXT_CONFIGURATOR_TAG_NAME) as $serviceId => $tag) {
            $priority = $tag[0]['priority'] ?? 0;

            $configurators[$priority][] = $serviceId;
        }
        if (!empty($configurators)) {
            ksort($configurators);
            $configurators = array_merge(...$configurators);
        }

        return $configurators;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getDataProviders(ContainerBuilder $container)
    {
        $dataProviders = [];
        foreach ($container->findTaggedServiceIds(self::DATA_PROVIDER_TAG_NAME) as $serviceId => $tag) {
            if (empty($tag[0]['alias'])) {
                throw new InvalidConfigurationException(
                    sprintf('Tag attribute "alias" is required for "%s" service.', $serviceId)
                );
            }

            $alias = $tag[0]['alias'];
            $dataProviders[$alias] = $serviceId;
        }

        return $dataProviders;
    }
}
