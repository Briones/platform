<?php

namespace Oro\Bundle\ApiBundle\Command;

use Oro\Bundle\ApiBundle\Config\Config;
use Oro\Bundle\ApiBundle\Provider\ConfigProvider;
use Oro\Bundle\ApiBundle\Provider\RelationConfigProvider;
use Oro\Bundle\ApiBundle\Provider\ResourcesProvider;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Request\Version;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Component\ChainProcessor\ProcessorBagInterface;
use ProxyManager\Proxy\VirtualProxyInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The CLI command to show configuration of Data API resources.
 */
class DumpConfigCommand extends AbstractDebugCommand
{
    /** @var string */
    protected static $defaultName = 'oro:api:config:dump';

    /** @var ProcessorBagInterface */
    private $processorBag;

    /** @var ConfigProvider */
    private $configProvider;

    /** @var RelationConfigProvider */
    private $relationConfigProvider;

    /**
     * @var array
     */
    protected $knownExtras = [
        'Oro\Bundle\ApiBundle\Config\EntityDefinitionConfigExtra',
        'Oro\Bundle\ApiBundle\Config\FiltersConfigExtra',
        'Oro\Bundle\ApiBundle\Config\SortersConfigExtra',
        'Oro\Bundle\ApiBundle\Config\DescriptionsConfigExtra',
        'Oro\Bundle\ApiBundle\Config\CustomizeLoadedDataConfigExtra',
        'Oro\Bundle\ApiBundle\Config\DataTransformersConfigExtra',
    ];

    /**
     * @param ValueNormalizer $valueNormalizer
     * @param ResourcesProvider $resourcesProvider
     * @param ProcessorBagInterface $processorBag
     * @param ConfigProvider $configProvider
     * @param RelationConfigProvider $relationConfigProvider
     */
    public function __construct(
        ValueNormalizer $valueNormalizer,
        ResourcesProvider $resourcesProvider,
        ProcessorBagInterface $processorBag,
        ConfigProvider $configProvider,
        RelationConfigProvider $relationConfigProvider
    ) {
        parent::__construct($valueNormalizer, $resourcesProvider);

        $this->processorBag = $processorBag;
        $this->configProvider = $configProvider;
        $this->relationConfigProvider = $relationConfigProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Dumps entity configuration used in Data API.')
            ->addArgument(
                'entity',
                InputArgument::OPTIONAL,
                'The entity class name or alias'
            )
            ->addOption(
                'section',
                null,
                InputOption::VALUE_REQUIRED,
                'The configuration section. Can be "entities" or "relations"',
                'entities'
            )
            ->addOption(
                'extra',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'The kind of configuration data that should be displayed. ' .
                sprintf(
                    'Can be %s or the full name of a class implements "%s"',
                    '"' . implode('", "', array_keys($this->getKnownExtras())) . '"',
                    'Oro\Bundle\ApiBundle\Config\ConfigExtraInterface'
                ),
                ['definition', 'filters', 'sorters', 'customize_loaded_data', 'data_transformers']
            )
            ->addOption(
                'action',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of action for which the configuration should be displayed.' .
                'Can be "get", "get_list", "create", "update", "delete", "delete_list", etc.',
                'get'
            )
            ->addOption(
                'documentation-resources',
                null,
                InputOption::VALUE_NONE,
                'Shows the list of documentation resources'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $requestType = $this->getRequestType($input);
        // API version is not supported for now
        $version = Version::normalizeVersion(null);
        $extras = $this->getConfigExtras($input);

        $this->processorBag->addApplicableChecker(new Util\RequestTypeApplicableChecker());

        $entityClass = $this->resolveEntityClass($input->getArgument('entity'), $version, $requestType);
        $isDocumentationResourcesRequested = $input->getOption('documentation-resources');
        if ($isDocumentationResourcesRequested && !$entityClass) {
            $entityClasses = $this->getEntityClasses($version, $requestType);
            foreach ($entityClasses as $entityClass) {
                $config = $this->getConfig($entityClass, $version, $requestType, $extras);
                $config = $this->getDocumentationResources($config);
                $this->dumpConfig($output, $config);
            }
        } else {
            if (!$entityClass) {
                throw new RuntimeException('The "entity" argument is missing.');
            }

            switch ($input->getOption('section')) {
                case 'entities':
                    $config = $this->getConfig($entityClass, $version, $requestType, $extras);
                    break;
                case 'relations':
                    $config = $this->getRelationConfig($entityClass, $version, $requestType, $extras);
                    break;
                default:
                    throw new \InvalidArgumentException(
                        'The section should be either "entities" or "relations".'
                    );
            }

            if ($isDocumentationResourcesRequested) {
                $config = $this->getDocumentationResources($config);
            }

            $this->dumpConfig($output, $config);
        }
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     */
    protected function getConfigExtras(InputInterface $input)
    {
        $result = [];

        $knownExtras = $this->getKnownExtras();
        $extraNames = $input->getOption('extra');
        foreach ($extraNames as $extraName) {
            $extraClassName = null;
            if (array_key_exists($extraName, $knownExtras)) {
                $extraClassName = $knownExtras[$extraName];
            } else {
                if (false === strpos($extraName, '\\')) {
                    throw new \InvalidArgumentException(
                        sprintf('Unknown value "%s" for the "--extra" option.', $extraName)
                    );
                }
                if (!class_exists($extraName)) {
                    throw new \InvalidArgumentException(
                        sprintf('The class "%s" passed as value for the "--extra" option not found.', $extraName)
                    );
                }
                if (!is_a($extraName, 'Oro\Bundle\ApiBundle\Config\ConfigExtraInterface', true)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'The class "%s" passed as value for the "--extra" option must implement "%s".',
                            $extraName,
                            'Oro\Bundle\ApiBundle\Config\ConfigExtraInterface'
                        )
                    );
                }
                $extraClassName = $extraName;
            }

            if ('Oro\Bundle\ApiBundle\Config\EntityDefinitionConfigExtra' === $extraClassName) {
                $action = $input->getOption('action');
                $result[] = new $extraClassName($action);
            } else {
                $result[] = new $extraClassName();
            }
        }

        return $result;
    }

    /**
     * @param string      $entityClass
     * @param string      $version
     * @param RequestType $requestType
     * @param array       $extras
     *
     * @return array
     */
    protected function getConfig($entityClass, $version, RequestType $requestType, array $extras)
    {
        $config = $this->configProvider->getConfig($entityClass, $version, $requestType, $extras);

        return [
            $entityClass => $this->convertConfigToArray($config)
        ];
    }

    /**
     * @param string      $entityClass
     * @param string      $version
     * @param RequestType $requestType
     * @param array       $extras
     *
     * @return array
     */
    protected function getRelationConfig($entityClass, $version, RequestType $requestType, array $extras)
    {
        $config = $this->relationConfigProvider->getRelationConfig($entityClass, $version, $requestType, $extras);

        return [
            $entityClass => $this->convertConfigToArray($config)
        ];
    }

    /**
     * @param string      $version
     * @param RequestType $requestType
     *
     * @return array
     */
    protected function getEntityClasses($version, RequestType $requestType)
    {
        $resources = $this->resourcesProvider->getResources($version, $requestType);
        $entityClasses = [];
        foreach ($resources as $resource) {
            $entityClasses[] = $resource->getEntityClass();
        }
        sort($entityClasses);

        return $entityClasses;
    }

    /**
     * @param Config $config
     *
     * @return array
     */
    protected function convertConfigToArray(Config $config)
    {
        $result = [];

        $data = $config->toArray();

        // add known sections in predefined order
        if (!empty($data[ConfigUtil::DEFINITION])) {
            $result = $data[ConfigUtil::DEFINITION];
        }
        unset($data[ConfigUtil::DEFINITION]);
        foreach ([ConfigUtil::FILTERS, ConfigUtil::SORTERS] as $sectionName) {
            if (array_key_exists($sectionName, $data)) {
                $result[$sectionName] = $data[$sectionName];
            }
        }
        // add other sections
        foreach ($data as $sectionName => $sectionConfig) {
            if (!array_key_exists($sectionName, $result)) {
                $result[$sectionName] = $sectionConfig;
            }
        }

        return $result;
    }

    /**
     * @return string[] [extra name => extra class name]
     */
    protected function getKnownExtras()
    {
        $result = [];
        foreach ($this->knownExtras as $className) {
            $result[constant($className . '::NAME')] = $className;
        }

        return $result;
    }

    /**
     * @param mixed $val
     *
     * @return mixed
     */
    protected function convertConfigValueToHumanReadableRepresentation($val)
    {
        if ($val instanceof \Closure) {
            $val = sprintf(
                'closure from %s',
                (new \ReflectionFunction($val))->getClosureScopeClass()->getName()
            );
        } elseif (is_object($val)) {
            if ($val instanceof VirtualProxyInterface) {
                if (!$val->isProxyInitialized()) {
                    $val->initializeProxy();
                }
                $val = $val->getWrappedValueHolderValue();
            }
            $val = sprintf('instance of %s', get_class($val));
        }

        return $val;
    }

    /**
     * @param OutputInterface $output
     * @param array           $config
     */
    private function dumpConfig(OutputInterface $output, array $config)
    {
        array_walk_recursive(
            $config,
            function (&$val) {
                $val = $this->convertConfigValueToHumanReadableRepresentation($val);
            }
        );
        $output->write(Yaml::dump($config, 100, 4, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_OBJECT));
    }

    /**
     * @param array $config
     *
     * @return array
     */
    private function getDocumentationResources(array $config)
    {
        $keys = array_keys($config);
        $entityClass = reset($keys);
        $config = $config[$entityClass];
        $documentationResource = [];
        if (array_key_exists(ConfigUtil::DOCUMENTATION_RESOURCE, $config)) {
            $documentationResource = $config[ConfigUtil::DOCUMENTATION_RESOURCE];
        }

        return [
            $entityClass => [
                ConfigUtil::DOCUMENTATION_RESOURCE => $documentationResource
            ]
        ];
    }
}
