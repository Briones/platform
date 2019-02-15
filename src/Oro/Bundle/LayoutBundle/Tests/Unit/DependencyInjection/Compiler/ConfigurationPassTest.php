<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\LayoutBundle\DependencyInjection\Compiler\ConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ConfigurationPassTest extends \PHPUnit\Framework\TestCase
{
    /** @var ConfigurationPass */
    private $compiler;

    protected function setUp()
    {
        $this->compiler = new ConfigurationPass();
    }

    /**
     * @return ContainerBuilder
     */
    private function getContainer()
    {
        $container = new ContainerBuilder();
        $container->register('oro_layout.theme_extension.configuration');
        $container->register('oro_layout.layout_factory_builder');
        $container->register('oro_layout.extension')
            ->setArguments([
                new Reference('service_container'),
                [],
                [],
                [],
                [],
                []
            ]);

        return $container;
    }

    public function testRegisterThemeConfigExtensions()
    {
        $container = $this->getContainer();

        $container->register('theme_config_extension1')
            ->addTag('layout.theme_config_extension');

        $this->compiler->process($container);

        self::assertEquals(
            [
                ['addExtension', [new Reference('theme_config_extension1')]]
            ],
            $container->getDefinition('oro_layout.theme_extension.configuration')->getMethodCalls()
        );
    }

    public function testRegisterThemeConfigExtensionsWhenNoExtensions()
    {
        $container = $this->getContainer();

        $this->compiler->process($container);

        self::assertEquals(
            [],
            $container->getDefinition('oro_layout.theme_extension.configuration')->getMethodCalls()
        );
    }

    public function testConfigureLayoutExtension()
    {
        $container = $this->getContainer();

        $container->register('block1', 'Test\BlockType1')
            ->addTag('layout.block_type', ['alias' => 'test_block_name1']);
        $container->register('block2', 'Test\BlockType2')
            ->addTag('layout.block_type', ['alias' => 'test_block_name2']);

        $container->register('extension1', 'Test\BlockTypeExtension1')
            ->addTag('layout.block_type_extension', ['alias' => 'test_block_name1']);
        $container->register('extension2', 'Test\BlockTypeExtension1')
            ->addTag('layout.block_type_extension', ['alias' => 'test_block_name2']);
        $container->register('extension3', 'Test\BlockTypeExtension1')
            ->addTag('layout.block_type_extension', ['alias' => 'test_block_name1', 'priority' => -10]);

        $container->register('update1', 'Test\LayoutUpdate1')
            ->addTag('layout.layout_update', ['id' => 'test_block_id1']);
        $container->register('update2', 'Test\LayoutUpdate2')
            ->addTag('layout.layout_update', ['id' => 'test_block_id2']);
        $container->register('update3', 'Test\LayoutUpdate3')
            ->addTag('layout.layout_update', ['id' => 'test_block_id1', 'priority' => -10]);

        $container->register('contextConfigurator1', 'Test\ContextConfigurator1')
            ->addTag('layout.context_configurator');
        $container->register('contextConfigurator2', 'Test\ContextConfigurator3')
            ->addTag('layout.context_configurator', ['priority' => -10]);
        $container->register('contextConfigurator3', 'Test\ContextConfigurator3')
            ->addTag('layout.context_configurator');

        $container->register('dataProvider1', 'Test\DataProvider1')
            ->addTag('layout.data_provider', ['alias' => 'test_data_provider_name1']);
        $container->register('dataProvider2', 'Test\DataProvider2')
            ->addTag('layout.data_provider', ['alias' => 'test_data_provider_name2']);

        $this->compiler->process($container);

        $extensionDef = $container->getDefinition('oro_layout.extension');
        self::assertEquals(
            [
                'test_block_name1' => 'block1',
                'test_block_name2' => 'block2'
            ],
            $extensionDef->getArgument(1)
        );
        self::assertEquals(
            [
                'test_block_name1' => ['extension3', 'extension1'],
                'test_block_name2' => ['extension2']
            ],
            $extensionDef->getArgument(2)
        );
        self::assertEquals(
            [
                'test_block_id1' => ['update3', 'update1'],
                'test_block_id2' => ['update2']
            ],
            $extensionDef->getArgument(3)
        );
        self::assertEquals(
            [
                'contextConfigurator2',
                'contextConfigurator1',
                'contextConfigurator3'
            ],
            $extensionDef->getArgument(4)
        );
        self::assertEquals(
            [
                'test_data_provider_name1' => 'dataProvider1',
                'test_data_provider_name2' => 'dataProvider2'
            ],
            $extensionDef->getArgument(5)
        );
    }

    public function testRegisterRenderers()
    {
        $container = $this->getContainer();

        $container->register('oro_layout.php.layout_renderer');
        $container->register('oro_layout.twig.layout_renderer');

        $this->compiler->process($container);

        self::assertEquals(
            [
                ['addRenderer', ['php', new Reference('oro_layout.php.layout_renderer')]],
                ['addRenderer', ['twig', new Reference('oro_layout.twig.layout_renderer')]]
            ],
            $container->getDefinition('oro_layout.layout_factory_builder')->getMethodCalls()
        );
    }

    public function testRegisterRenderersWhenNoRenderers()
    {
        $container = $this->getContainer();

        $this->compiler->process($container);

        self::assertEquals(
            [],
            $container->getDefinition('oro_layout.layout_factory_builder')->getMethodCalls()
        );
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Tag attribute "alias" is required for "block1" service.
     */
    public function testBlockTypeWithoutAlias()
    {
        $container = $this->getContainer();

        $container->register('block1', 'Test\Class1')
            ->addTag('layout.block_type');

        $this->compiler->process($container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Tag attribute "alias" is required for "extension1" service.
     */
    public function testBlockTypeExtensionWithoutAlias()
    {
        $container = $this->getContainer();

        $container->register('extension1', 'Test\Class1')
            ->addTag('layout.block_type_extension');

        $this->compiler->process($container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Tag attribute "id" is required for "update1" service.
     */
    public function testLayoutUpdateWithoutId()
    {
        $container = $this->getContainer();

        $container->register('update1', 'Test\Class1')
            ->addTag('layout.layout_update');

        $this->compiler->process($container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Tag attribute "alias" is required for "dataProvider1" service.
     */
    public function testDataProviderWithoutAlias()
    {
        $container = $this->getContainer();

        $container->register('dataProvider1', 'Test\DataProvider1')
            ->addTag('layout.data_provider');

        $this->compiler->process($container);
    }
}
