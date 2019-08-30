<?php

namespace Oro\Bundle\ScopeBundle\Tests\Unit\Manager;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\ScopeBundle\Entity\Repository\ScopeRepository;
use Oro\Bundle\ScopeBundle\Entity\Scope;
use Oro\Bundle\ScopeBundle\Manager\ScopeEntityStorage;
use Oro\Bundle\ScopeBundle\Manager\ScopeManager;
use Oro\Bundle\ScopeBundle\Model\ScopeCriteria;
use Oro\Bundle\ScopeBundle\Tests\Unit\Stub\StubContext;
use Oro\Bundle\ScopeBundle\Tests\Unit\Stub\StubScope;
use Oro\Bundle\ScopeBundle\Tests\Unit\Stub\StubScopeCriteriaProvider;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class ScopeManagerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ScopeEntityStorage|\PHPUnit\Framework\MockObject\MockObject */
    private $entityStorage;

    /** @var ClassMetadata|\PHPUnit\Framework\MockObject\MockObject */
    private $scopeClassMetadata;

    /** @var ScopeRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $scopeRepository;

    protected function setUp()
    {
        $this->entityStorage = $this->createMock(ScopeEntityStorage::class);
        $this->scopeClassMetadata = $this->createMock(ClassMetadata::class);
        $this->scopeRepository = $this->createMock(ScopeRepository::class);

        $this->entityStorage->expects($this->any())
            ->method('getClassMetadata')
            ->willReturn($this->scopeClassMetadata);
        $this->entityStorage->expects($this->any())
            ->method('getRepository')
            ->willReturn($this->scopeRepository);
    }

    /**
     * @param array $providers
     *
     * @return ScopeManager
     */
    private function getScopeManager(array $providers = []): ScopeManager
    {
        $serviceMap = [];
        $providerIds = [];
        foreach ($providers as $scopeType => $services) {
            $serviceIds = [];
            foreach ($services as $key => $service) {
                $serviceId = sprintf('%s_%s', $scopeType, $key);
                $serviceIds[] = $serviceId;
                $serviceMap[$serviceId] = $service;
            }
            $providerIds[$scopeType] = $serviceIds;
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($serviceId) use ($serviceMap) {
                if (!isset($serviceMap[$serviceId])) {
                    throw new ServiceNotFoundException($serviceId);
                }

                return $serviceMap[$serviceId];
            });

        return new ScopeManager(
            $container,
            $providerIds,
            $this->entityStorage,
            new PropertyAccessor()
        );
    }

    public function testFindDefaultScope()
    {
        $expectedCriteria = new ScopeCriteria(['relation' => null], $this->scopeClassMetadata);
        $scope = new Scope();

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['relation']);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($expectedCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager();
        $this->assertSame($scope, $manager->findDefaultScope());
    }

    public function testGetCriteriaByScope()
    {
        $scope = new StubScope();
        $scope->setScopeField('expected_value');

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn([]);

        $provider = new StubScopeCriteriaProvider('scopeField', new \stdClass(), \stdClass::class);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertSame(
            [$provider->getCriteriaField() => 'expected_value'],
            $manager->getCriteriaByScope($scope, 'testScope')->toArray()
        );
    }

    public function testFind()
    {
        $scope = new Scope();
        $fieldValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria(
            ['fieldName' => $fieldValue, 'fieldName2' => null],
            $this->scopeClassMetadata
        );

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['fieldName', 'fieldName2']);

        $provider = new StubScopeCriteriaProvider('fieldName', $fieldValue, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->find('testScope'));
    }

    public function testFindWithArrayContext()
    {
        $scope = new Scope();
        $fieldValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria(
            ['field' => $fieldValue, 'field2' => null],
            $this->scopeClassMetadata
        );
        $context = ['field' => $fieldValue];

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['field', 'field2']);

        $provider = new StubScopeCriteriaProvider('field', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->find('testScope', $context));
    }

    public function testFindWithObjectContext()
    {
        $scope = new Scope();
        $fieldValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria(
            ['field' => $fieldValue, 'field2' => null],
            $this->scopeClassMetadata
        );
        $context = new StubContext();
        $context->setField($fieldValue);

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['field', 'field2']);

        $provider = new StubScopeCriteriaProvider('field', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->find('testScope', $context));
    }

    public function testFindWithIsNotNullValueInContext()
    {
        $scope = new Scope();
        $fieldValue = ScopeCriteria::IS_NOT_NULL;
        $scopeCriteria = new ScopeCriteria(
            ['field' => $fieldValue, 'field2' => null],
            $this->scopeClassMetadata
        );
        $context = ['field' => $fieldValue];

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['field', 'field2']);

        $provider = new StubScopeCriteriaProvider('field', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->find('testScope', $context));
    }

    public function testFindWithArrayValueInContext()
    {
        $scope = new Scope();
        $fieldValue = [1, 2, 3];
        $scopeCriteria = new ScopeCriteria(
            ['field' => $fieldValue, 'field2' => null],
            $this->scopeClassMetadata
        );
        $context = ['field' => $fieldValue];

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['field', 'field2']);

        $provider = new StubScopeCriteriaProvider('field', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->find('testScope', $context));
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \Oro\Bundle\ScopeBundle\Exception\NotSupportedCriteriaValueException
     * @expectedExceptionMessage The type string is not supported for context[field]. Expected stdClass, null, array or "IS_NOT_NULL".
     */
    // @codingStandardsIgnoreEnd
    public function testFindWithInvalidScalarValueInContext()
    {
        $context = ['field' => 'test'];

        $provider = new StubScopeCriteriaProvider('field', null, \stdClass::class);

        $this->scopeRepository->expects($this->never())
            ->method('findOneByCriteria');

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $manager->find('testScope', $context);
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \Oro\Bundle\ScopeBundle\Exception\NotSupportedCriteriaValueException
     * @expectedExceptionMessage The type Oro\Bundle\ScopeBundle\Tests\Unit\Stub\StubScope is not supported for context[field]. Expected stdClass, null, array or "IS_NOT_NULL".
     */
    // @codingStandardsIgnoreEnd
    public function testFindWithInvalidObjectValueInContext()
    {
        $context = ['field' => new StubScope()];

        $provider = new StubScopeCriteriaProvider('field', null, \stdClass::class);

        $this->scopeRepository->expects($this->never())
            ->method('findOneByCriteria');

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $manager->find('testScope', $context);
    }

    public function testFindScheduled()
    {
        $scope = new Scope();
        $fieldValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria(
            ['fieldName' => $fieldValue, 'fieldName2' => null],
            $this->scopeClassMetadata
        );

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['fieldName', 'fieldName2']);

        $provider = new StubScopeCriteriaProvider('fieldName', $fieldValue, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->with($scopeCriteria);

        $this->entityStorage->expects($this->once())
            ->method('getScheduledForInsertByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->find('testScope'));
    }

    public function testCreateScopeByCriteriaWithFlush()
    {
        $scopeCriteria = new ScopeCriteria([], $this->scopeClassMetadata);

        $this->entityStorage->expects($this->once())
            ->method('getScheduledForInsertByCriteria')
            ->with($scopeCriteria)
            ->willReturn(null);
        $this->entityStorage->expects($this->once())
            ->method('scheduleForInsert')
            ->with($this->isInstanceOf(Scope::class), $scopeCriteria);
        $this->entityStorage->expects($this->once())
            ->method('flush');

        $manager = $this->getScopeManager();
        $this->assertInstanceOf(Scope::class, $manager->createScopeByCriteria($scopeCriteria));
    }

    public function testCreateScopeByCriteriaWithoutFlush()
    {
        $scopeCriteria = new ScopeCriteria([], $this->scopeClassMetadata);

        $this->entityStorage->expects($this->once())
            ->method('getScheduledForInsertByCriteria')
            ->with($scopeCriteria)
            ->willReturn(null);
        $this->entityStorage->expects($this->once())
            ->method('scheduleForInsert')
            ->with($this->isInstanceOf(Scope::class), $scopeCriteria);
        $this->entityStorage->expects($this->never())
            ->method('flush');

        $manager = $this->getScopeManager();
        $this->assertInstanceOf(Scope::class, $manager->createScopeByCriteria($scopeCriteria, false));
    }

    public function testCreateScopeByCriteriaScheduled()
    {
        $scope = new Scope();
        $scopeCriteria = new ScopeCriteria([], $this->scopeClassMetadata);

        $this->entityStorage->expects($this->once())
            ->method('getScheduledForInsertByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scope);
        $this->entityStorage->expects($this->never())
            ->method('scheduleForInsert');
        $this->entityStorage->expects($this->never())
            ->method('flush');

        $manager = $this->getScopeManager();
        $this->assertEquals($scope, $manager->createScopeByCriteria($scopeCriteria));
    }

    public function testFindBy()
    {
        $scope = new Scope();
        $criteriaField = 'scopeField';
        $criteriaValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria([$criteriaField => $criteriaValue], $this->scopeClassMetadata);

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn([$criteriaField]);

        $provider = new StubScopeCriteriaProvider($criteriaField, $criteriaValue, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findByCriteria')
            ->with($scopeCriteria)
            ->willReturn([$scope]);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals([$scope], $manager->findBy('testScope'));
    }

    public function testFindRelatedScopes()
    {
        $scopes = [new Scope()];
        $scopeCriteria = new ScopeCriteria(
            ['fieldName' => ScopeCriteria::IS_NOT_NULL, 'fieldName2' => null],
            $this->scopeClassMetadata
        );

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['fieldName', 'fieldName2']);

        $provider = new StubScopeCriteriaProvider('fieldName', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scopes);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertSame($scopes, $manager->findRelatedScopes('testScope'));
    }

    public function testFindRelatedScopeIds()
    {
        $scopeIds = [1, 4];
        $scopeCriteria = new ScopeCriteria(
            ['fieldName' => ScopeCriteria::IS_NOT_NULL, 'fieldName2' => null],
            $this->scopeClassMetadata
        );

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['fieldName', 'fieldName2']);

        $provider = new StubScopeCriteriaProvider('fieldName', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findIdentifiersByCriteria')
            ->with($scopeCriteria)
            ->willReturn($scopeIds);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertSame($scopeIds, $manager->findRelatedScopeIds('testScope'));
    }

    public function testFindRelatedScopeIdsWithPriority()
    {
        $scopes = [new Scope()];
        $fieldValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria(
            ['fieldName' => $fieldValue, 'fieldName2' => null],
            $this->scopeClassMetadata
        );

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['fieldName', 'fieldName2']);

        $provider = new StubScopeCriteriaProvider('fieldName', $fieldValue, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findIdentifiersByCriteriaWithPriority')
            ->with($scopeCriteria)
            ->willReturn($scopes);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertSame($scopes, $manager->findRelatedScopeIdsWithPriority('testScope'));
    }

    public function testFindOrCreate()
    {
        $scope = new Scope();

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn([]);

        $provider = new StubScopeCriteriaProvider('fieldName', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->willReturn(null);

        $this->entityStorage->expects($this->once())
            ->method('scheduleForInsert')
            ->with($scope, $this->isInstanceOf(ScopeCriteria::class));
        $this->entityStorage->expects($this->once())
            ->method('flush');

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->findOrCreate('testScope'));
    }

    public function testFindOrCreateWithoutFlush()
    {
        $scope = new Scope();

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn([]);

        $provider = new StubScopeCriteriaProvider('fieldName', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->willReturn(null);

        $this->entityStorage->expects($this->once())
            ->method('scheduleForInsert')
            ->with($scope, $this->isInstanceOf(ScopeCriteria::class));
        $this->entityStorage->expects($this->never())
            ->method('flush');

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->findOrCreate('testScope', null, false));
    }

    public function testFindOrCreateUsingContext()
    {
        $scope = new Scope();
        $context = ['scopeAttribute' => new \stdClass()];

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn([]);

        $provider = new StubScopeCriteriaProvider('fieldName', null, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findOneByCriteria')
            ->willReturn(null);

        $this->entityStorage->expects($this->once())
            ->method('scheduleForInsert')
            ->with($scope, $this->isInstanceOf(ScopeCriteria::class));
        $this->entityStorage->expects($this->once())
            ->method('flush');

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->findOrCreate('testScope', $context));
    }

    public function testGetScopeEntities()
    {
        $provider = new StubScopeCriteriaProvider('scopeField', null, \stdClass::class);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals(
            [
                $provider->getCriteriaField() => $provider->getCriteriaValueType()
            ],
            $manager->getScopeEntities('testScope')
        );
    }

    public function testFindMostSuitable()
    {
        $scope = new Scope();
        $fieldValue = new \stdClass();
        $scopeCriteria = new ScopeCriteria(
            ['fieldName' => $fieldValue, 'fieldName2' => null],
            $this->scopeClassMetadata
        );

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn(['fieldName', 'fieldName2']);

        $provider = new StubScopeCriteriaProvider('fieldName', $fieldValue, \stdClass::class);

        $this->scopeRepository->expects($this->once())
            ->method('findMostSuitable')
            ->with($scopeCriteria)
            ->willReturn($scope);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals($scope, $manager->findMostSuitable('testScope'));
    }

    /**
     * @dataProvider isScopeMatchCriteriaDataProvider
     *
     * @param $expectedResult
     * @param $criteriaContext
     * @param $scopeFieldValue
     */
    public function testIsScopeMatchCriteria($expectedResult, $criteriaContext, $scopeFieldValue)
    {
        $scope = new StubScope();
        $scope->setScopeField($scopeFieldValue);
        $scopeCriteria = new ScopeCriteria($criteriaContext, $this->scopeClassMetadata);

        $this->scopeClassMetadata->expects($this->once())
            ->method('getAssociationNames')
            ->willReturn([]);

        $provider = new StubScopeCriteriaProvider('scopeField', new \stdClass(), \stdClass::class);

        $manager = $this->getScopeManager(['testScope' => [$provider]]);
        $this->assertEquals(
            $expectedResult,
            $manager->isScopeMatchCriteria($scope, $scopeCriteria, 'testScope')
        );
    }

    /**
     * @return array
     */
    public function isScopeMatchCriteriaDataProvider()
    {
        return [
            'scope match criteria'                             => [
                'expectedResult'  => true,
                'criteriaContext' => ['scopeField' => 'expected_value'],
                'scopeFieldValue' => 'expected_value'
            ],
            'scope dont match criteria'                        => [
                'expectedResult'  => false,
                'criteriaContext' => ['scopeField' => 'unexpected_value'],
                'scopeFieldValue' => 'expected_value'
            ],
            'scope match criteria with null value'             => [
                'expectedResult'  => true,
                'criteriaContext' => ['scopeField' => 'unexpected_value'],
                'scopeFieldValue' => null
            ],
            'scope dont match criteria with different objects' => [
                'expectedResult'  => false,
                'criteriaContext' => ['scopeField' => new \stdClass()],
                'scopeFieldValue' => new \stdClass()
            ]
        ];
    }
}
