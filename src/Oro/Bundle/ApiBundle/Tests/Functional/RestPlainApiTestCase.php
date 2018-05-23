<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Symfony\Component\HttpFoundation\Response;

/**
 * The base class for plain REST API functional tests.
 */
abstract class RestPlainApiTestCase extends RestApiTestCase
{
    const JSON_CONTENT_TYPE = 'application/json';

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function getRequestType()
    {
        return new RequestType([RequestType::REST]);
    }

    /**
     * Sends REST API request.
     *
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param array  $server
     *
     * @return Response
     */
    protected function request($method, $uri, array $parameters = [], array $server = [])
    {
        if (!isset($server['HTTP_X-WSSE'])) {
            $server = array_replace($server, $this->getWsseAuthHeader());
        }

        $this->client->request(
            $method,
            $uri,
            $parameters,
            [],
            $server
        );

        return $this->client->getResponse();
    }

    /**
     * Asserts the response content contains the the given data.
     *
     * @param array|string $expectedContent The file name or full file path to YAML template file or array
     * @param Response     $response
     * @param object|null  $entity          If not null, object will set as entity reference
     */
    protected function assertResponseContains($expectedContent, Response $response, $entity = null)
    {
        if ($entity) {
            $this->getReferenceRepository()->addReference('entity', $entity);
        }

        $content = self::jsonToArray($response->getContent());
        $expectedContent = self::processTemplateData($this->loadResponseData($expectedContent));

        self::assertArrayContains($expectedContent, $content);
    }

    /**
     * Asserts the response content contains the the given validation error.
     *
     * @param array    $expectedError
     * @param Response $response
     */
    protected function assertResponseValidationError($expectedError, Response $response)
    {
        $this->assertResponseValidationErrors([$expectedError], $response);
    }

    /**
     * Asserts the response content contains the the given validation errors.
     *
     * @param array    $expectedErrors
     * @param Response $response
     */
    protected function assertResponseValidationErrors($expectedErrors, Response $response)
    {
        static::assertResponseStatusCodeEquals($response, Response::HTTP_BAD_REQUEST);

        $content = self::jsonToArray($response->getContent());
        try {
            $this->assertResponseContains($expectedErrors, $response);
            self::assertCount(
                count($expectedErrors),
                $content,
                'Unexpected number of validation errors'
            );
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            throw new \PHPUnit_Framework_ExpectationFailedException(
                sprintf(
                    "%s\nResponse:\n%s",
                    $e->getMessage(),
                    json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                ),
                $e->getComparisonFailure()
            );
        }
    }
}
