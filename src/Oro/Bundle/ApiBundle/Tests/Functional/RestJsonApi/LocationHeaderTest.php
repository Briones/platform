<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApi;

use Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiTestCase;
use Oro\Bundle\TestFrameworkBundle\Entity\TestProduct;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @dbIsolationPerTest
 */
class LocationHeaderTest extends RestJsonApiTestCase
{
    public function testPostShouldRerurnLocationHeader()
    {
        $entityType = $this->getEntityType(TestProduct::class);
        $response = $this->post(
            ['entity' => $entityType],
            ['data' => ['type' => $entityType, 'attributes' => ['name' => 'test']]]
        );
        self::assertTrue($response->headers->has('Location'));
        $locationUrl = $this->getUrl(
            $this->getItemRouteName(),
            ['entity' => $entityType, 'id' => $this->getResourceId($response)],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        self::assertEquals($locationUrl, $response->headers->get('Location'));
    }

    public function testPostShouldNotRerurnLocationHeaderIfNotSuccess()
    {
        $entityType = $this->getEntityType(TestProduct::class);
        $response = $this->post(
            ['entity' => $entityType],
            ['data' => ['type' => $entityType, 'attributes' => ['unknown' => 'test']]],
            [],
            false
        );
        self::assertResponseStatusCodeEquals($response, 400);
        self::assertFalse($response->headers->has('Location'));
    }
}
