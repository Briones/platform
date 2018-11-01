<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Extension\JsValidation;

use Oro\Bundle\AddressBundle\Extension\JsValidation\ConstraintsProviderDecorator;
use Oro\Bundle\AddressBundle\Validator\Constraints\NameOrOrganization;
use Oro\Bundle\FormBundle\Form\Extension\JsValidation\ConstraintsProvider;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConstraintsProviderDecoratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConstraintsProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $provider;

    /**
     * @var ConstraintsProviderDecorator
     */
    private $providerDecorator;

    protected function setUp()
    {
        $this->provider = $this->createMock(ConstraintsProvider::class);

        $this->providerDecorator = new ConstraintsProviderDecorator($this->provider);
    }

    public function testGetFormConstraintsNoParentForm()
    {
        $constraints = [new NameOrOrganization()];

        $this->provider
            ->expects($this->any())
            ->method('getFormConstraints')
            ->willReturn($constraints);

        /** @var FormInterface|\PHPUnit_Framework_MockObject_MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form
            ->expects($this->any())
            ->method('getParent')
            ->willReturn(null);

        self::assertEquals($constraints, $this->providerDecorator->getFormConstraints($form));
    }

    public function testGetFormConstraintsHasParentNoNameOrOrganization()
    {
        $constraints = [new NotBlank()];

        $this->provider
            ->expects($this->any())
            ->method('getFormConstraints')
            ->willReturn($constraints);

        /** @var FormInterface|\PHPUnit_Framework_MockObject_MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form
            ->expects($this->any())
            ->method('getParent')
            ->willReturn($this->createMock(FormInterface::class));

        self::assertEquals($constraints, $this->providerDecorator->getFormConstraints($form));
    }

    public function testGetFormConstraintsHasParentNoNameOrOrganizationAndNameOrOrganizationConstraint()
    {
        $constraints = [new NotBlank(), new NameOrOrganization()];

        $this->provider
            ->expects($this->any())
            ->method('getFormConstraints')
            ->willReturn($constraints);

        /** @var FormInterface|\PHPUnit_Framework_MockObject_MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form
            ->expects($this->any())
            ->method('getParent')
            ->willReturn($this->createMock(FormInterface::class));
        $form
            ->expects($this->any())
            ->method('getName')
            ->willReturn('parentName');

        $expectedConstraints = [new NotBlank(), new NameOrOrganization(['parentFormName' => 'parentName'])];
        self::assertEquals($expectedConstraints, $this->providerDecorator->getFormConstraints($form));
    }
}
