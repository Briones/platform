<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AttachmentBundle\Form\Type\FileItemType;
use Oro\Bundle\AttachmentBundle\Form\Type\MultiFileType;
use Oro\Bundle\FormBundle\Form\Type\CollectionType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

class MultiFileTypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var MultiFileType */
    protected $type;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->type = new MultiFileType();
    }

    public function testConfigureOptions()
    {
        /* @var $resolver OptionsResolver|\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects(self::once())
            ->method('setDefaults')
            ->with([
                'entry_type' => FileItemType::class,
                'constraints' => [
                    new Valid(),
                ],
            ]);

        $this->type->configureOptions($resolver);
    }

    public function testGetName()
    {
        $this->assertEquals(MultiFileType::TYPE, $this->type->getName());
    }

    public function testGetBlockPrefix()
    {
        $this->assertEquals(MultiFileType::TYPE, $this->type->getBlockPrefix());
    }

    public function testGetParent()
    {
        $this->assertEquals(CollectionType::class, $this->type->getParent());
    }
}
