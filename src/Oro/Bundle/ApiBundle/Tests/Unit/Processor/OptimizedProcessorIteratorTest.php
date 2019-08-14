<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor;

use Oro\Bundle\ApiBundle\Processor\OptimizedProcessorIterator;
use Oro\Bundle\ApiBundle\Processor\OptimizedProcessorIteratorFactory;
use Oro\Component\ChainProcessor\ApplicableCheckerInterface;
use Oro\Component\ChainProcessor\ChainApplicableChecker;
use Oro\Component\ChainProcessor\Context;
use Oro\Component\ChainProcessor\ProcessorBagInterface;
use Oro\Component\ChainProcessor\ProcessorRegistryInterface;
use Oro\Component\ChainProcessor\Tests\Unit\NotDisabledApplicableChecker;
use Oro\Component\ChainProcessor\Tests\Unit\ProcessorMock;

class OptimizedProcessorIteratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array                           $processors
     * @param string[]                        $groups
     * @param Context                         $context
     * @param ApplicableCheckerInterface|null $applicableChecker
     *
     * @return OptimizedProcessorIterator
     */
    private function getOptimizedProcessorIterator(
        array $processors,
        array $groups,
        Context $context,
        ApplicableCheckerInterface $applicableChecker = null
    ) {
        $chainApplicableChecker = new ChainApplicableChecker();
        if ($applicableChecker) {
            $chainApplicableChecker->addChecker($applicableChecker);
        }

        $factory = new OptimizedProcessorIteratorFactory();
        $processorBag = $this->createMock(ProcessorBagInterface::class);
        $factory->setProcessorBag($processorBag);
        $processorBag->expects(self::any())
            ->method('getActionGroups')
            ->with($context->getAction())
            ->willReturn($groups);

        return $factory->createProcessorIterator(
            $processors,
            $context,
            $chainApplicableChecker,
            $this->getProcessorRegistry()
        );
    }

    /**
     * @return Context
     */
    private function getContext()
    {
        $context = new Context();
        $context->setAction('test');

        return $context;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ProcessorRegistryInterface
     */
    private function getProcessorRegistry()
    {
        $processorRegistry = $this->createMock(ProcessorRegistryInterface::class);
        $processorRegistry->expects(self::any())
            ->method('getProcessor')
            ->willReturnCallback(
                function ($processorId) {
                    return new ProcessorMock($processorId);
                }
            );

        return $processorRegistry;
    }

    /**
     * @param string[]  $expectedProcessorIds
     * @param \Iterator $processors
     */
    private function assertProcessors(array $expectedProcessorIds, \Iterator $processors)
    {
        $processorIds = [];
        /** @var ProcessorMock $processor */
        foreach ($processors as $processor) {
            $processorIds[] = $processor->getProcessorId();
        }

        self::assertEquals($expectedProcessorIds, $processorIds);
    }

    public function testNoApplicableRules()
    {
        $context = $this->getContext();

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor3'
            ],
            $iterator
        );
    }

    public function testNoGroupRelatedApplicableRules()
    {
        $context = $this->getContext();

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1', 'disabled' => true]],
            ['processor4', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator(
            $processors,
            ['group1'],
            $context,
            new NotDisabledApplicableChecker()
        );

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor4'
            ],
            $iterator
        );
    }

    public function testSkipGroups()
    {
        $context = $this->getContext();
        $context->skipGroup('group1');
        $context->skipGroup('group3');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group2']],
            ['processor6', ['group' => 'group3']],
            ['processor7', ['group' => 'group3']],
            ['processor8', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor4',
                'processor5',
                'processor8'
            ],
            $iterator
        );
    }

    public function testSkipGroupsWithoutUngroupedProcessors()
    {
        $context = $this->getContext();
        $context->skipGroup('group1');
        $context->skipGroup('group3');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group2']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group3']],
            ['processor6', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor3',
                'processor4'
            ],
            $iterator
        );
    }

    public function testLastGroup()
    {
        $context = $this->getContext();
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group2']],
            ['processor6', ['group' => 'group3']],
            ['processor7', ['group' => 'group3']],
            ['processor8', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor3',
                'processor4',
                'processor5',
                'processor8'
            ],
            $iterator
        );
    }

    public function testUnknownLastGroup()
    {
        $context = $this->getContext();
        $context->setLastGroup('unknown_group');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor3'
            ],
            $iterator
        );
    }

    public function testLastGroupWithoutUngroupedProcessors()
    {
        $context = $this->getContext();
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group2']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group3']],
            ['processor6', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor3',
                'processor4'
            ],
            $iterator
        );
    }

    public function testCombinationOfLastGroupAndSkipGroup()
    {
        $context = $this->getContext();
        $context->skipGroup('group1');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group2']],
            ['processor6', ['group' => 'group3']],
            ['processor7', ['group' => 'group3']],
            ['processor8', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor4',
                'processor5',
                'processor8'
            ],
            $iterator
        );
    }

    public function testLastGroupShouldBeSkipped()
    {
        $context = $this->getContext();
        $context->skipGroup('group1');
        $context->skipGroup('group2');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group2']],
            ['processor6', ['group' => 'group3']],
            ['processor7', ['group' => 'group3']],
            ['processor8', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor8'
            ],
            $iterator
        );
    }

    public function testFirstGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group2']],
            ['processor6', ['group' => 'group3']],
            ['processor7', ['group' => 'group3']],
            ['processor8', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor4',
                'processor5',
                'processor6',
                'processor7',
                'processor8'
            ],
            $iterator
        );
    }

    public function testUnknownFirstGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('unknown_group');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor3'
            ],
            $iterator
        );
    }

    public function testFirstGroupWithoutUngroupedProcessors()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group2']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group3']],
            ['processor6', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor3',
                'processor4',
                'processor5',
                'processor6'
            ],
            $iterator
        );
    }

    public function testFirstGroupEqualsToLastGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', ['group' => 'group2']],
            ['processor6', ['group' => 'group3']],
            ['processor7', ['group' => 'group3']],
            ['processor8', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor4',
                'processor5',
                'processor8'
            ],
            $iterator
        );
    }

    public function testAllProcessorsFromLastGroupAreNotApplicable()
    {
        $context = $this->getContext();
        $context->setLastGroup('group1');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1', 'disabled' => true]],
            ['processor3', ['group' => 'group1', 'disabled' => true]],
            ['processor4', ['group' => 'group2']],
            ['processor5', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator(
            $processors,
            ['group1', 'group2'],
            $context,
            new NotDisabledApplicableChecker()
        );

        $this->assertProcessors(
            [
                'processor1',
                'processor5'
            ],
            $iterator
        );
    }

    public function testFirstProcessorFromLastGroupAreNotApplicable()
    {
        $context = $this->getContext();
        $context->setLastGroup('group1');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1', 'disabled' => true]],
            ['processor3', ['group' => 'group1']],
            ['processor4', ['group' => 'group2']],
            ['processor5', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator(
            $processors,
            ['group1', 'group2'],
            $context,
            new NotDisabledApplicableChecker()
        );

        $this->assertProcessors(
            [
                'processor1',
                'processor3',
                'processor5'
            ],
            $iterator
        );
    }

    public function testLastProcessorFromLastGroupAreNotApplicable()
    {
        $context = $this->getContext();
        $context->setLastGroup('group1');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group1', 'disabled' => true]],
            ['processor4', ['group' => 'group2']],
            ['processor5', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator(
            $processors,
            ['group1', 'group2'],
            $context,
            new NotDisabledApplicableChecker()
        );

        $this->assertProcessors(
            [
                'processor1',
                'processor2',
                'processor5'
            ],
            $iterator
        );
    }

    public function testFirstGroupAfterLastGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group4');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group2']],
            ['processor4', ['group' => 'group3']],
            ['processor5', ['group' => 'group4']],
            ['processor6', ['group' => 'group5']],
            ['processor7', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator(
            $processors,
            ['group1', 'group2', 'group3', 'group4', 'group5'],
            $context
        );

        $this->assertProcessors(
            [
                'processor1',
                'processor7'
            ],
            $iterator
        );
    }

    public function testFirstGroupAfterLastGroupWithoutUngroupedProcessors()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group4');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor2', ['group' => 'group2']],
            ['processor3', ['group' => 'group3']],
            ['processor4', ['group' => 'group4']],
            ['processor5', ['group' => 'group5']]
        ];

        $iterator = $this->getOptimizedProcessorIterator(
            $processors,
            ['group1', 'group2', 'group3', 'group4', 'group5'],
            $context
        );

        $this->assertProcessors(
            [],
            $iterator
        );
    }

    public function testFirstGroupEqualsLastGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', []],
            ['processor2', ['group' => 'group1']],
            ['processor3', ['group' => 'group2']],
            ['processor4', ['group' => 'group3']],
            ['processor5', []]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [
                'processor1',
                'processor3',
                'processor5'
            ],
            $iterator
        );
    }

    public function testFirstGroupEqualsLastGroupWithoutUngroupedProcessors()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor2', ['group' => 'group2']],
            ['processor3', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            ['processor2'],
            $iterator
        );
    }

    public function testFirstGroupEqualsLastGroupWithoutProcessorsInThisGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor3', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            [],
            $iterator
        );
    }

    public function testFirstGroupAndLastGroupWithoutProcessorsInFirstGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group2');
        $context->setLastGroup('group3');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor3', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            ['processor3'],
            $iterator
        );
    }

    public function testFirstGroupAndLastGroupWithoutProcessorsInLastGroup()
    {
        $context = $this->getContext();
        $context->setFirstGroup('group1');
        $context->setLastGroup('group2');

        $processors = [
            ['processor1', ['group' => 'group1']],
            ['processor3', ['group' => 'group3']]
        ];

        $iterator = $this->getOptimizedProcessorIterator($processors, ['group1', 'group2', 'group3'], $context);

        $this->assertProcessors(
            ['processor1'],
            $iterator
        );
    }
}
