<?php

namespace Oro\Bundle\NavigationBundle\Controller\Api;

use Doctrine\Common\Persistence\ObjectRepository;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oro\Bundle\NavigationBundle\Entity\Builder\ItemFactory;
use Oro\Bundle\NavigationBundle\Provider\NavigationItemsProvider;
use Oro\Bundle\NavigationBundle\Utils\PinbarTabUrlNormalizer;
use Oro\Bundle\UserBundle\Entity\AbstractUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Provides API actions for managing navigation items.
 *
 * @RouteResource("navigationitems")
 * @NamePrefix("oro_api_")
 */
class NavigationItemController extends FOSRestController
{
    /**
     * REST GET list
     *
     * @param string $type
     *
     * @ApiDoc(
     *  description="Get all Navigation items for user",
     *  resource=true
     * )
     * @return Response
     */
    public function getAction($type)
    {
        /** @var NavigationItemsProvider $navigationItemsProvider */
        $navigationItemsProvider = $this->container->get('oro_navigation.provider.navigation_items');
        $organization = $this->container->get('security.token_storage')->getToken()->getOrganizationContext();

        $items = $navigationItemsProvider->getNavigationItems($this->getUser(), $organization, $type);

        return $this->handleView(
            $this->view($items, \is_array($items) ? Codes::HTTP_OK : Codes::HTTP_NOT_FOUND)
        );
    }

    /**
     * REST POST
     *
     * @param Request $request
     * @param string $type
     *
     * @ApiDoc(
     *  description="Add Navigation item",
     *  resource=true
     * )
     * @return Response
     */
    public function postAction(Request $request, $type)
    {
        $params = $request->request->all();

        if (empty($params) || empty($params['type'])) {
            return $this->handleView(
                $this->view(
                    array('message' => 'Wrong JSON inside POST body'),
                    Codes::HTTP_BAD_REQUEST
                )
            );
        }

        $params['user'] = $this->getUser();
        $params['url']  = $this->normalizeUrl($params['url'], $params['type']);
        $params['organization'] = $this->container->get('security.token_storage')->getToken()->getOrganizationContext();

        /** @var $entity \Oro\Bundle\NavigationBundle\Entity\NavigationItemInterface */
        $entity = $this->getFactory()->createItem($type, $params);

        if (!$entity) {
            return $this->handleView($this->view(array(), Codes::HTTP_NOT_FOUND));
        }

        $errors = $this->validate($entity);
        if ($errors) {
            return $this->handleView(
                $this->view(['message' => implode(PHP_EOL, $errors)], Codes::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        $em = $this->getManager();

        $em->persist($entity);
        $em->flush();

        return $this->handleView(
            $this->view(['id' => $entity->getId(), 'url' => $params['url']], Codes::HTTP_CREATED)
        );
    }

    /**
     * @param mixed $entity
     *
     * @return array
     */
    private function validate($entity): array
    {
        $constraintViolationList = $this->get('validator')->validate($entity);
        /** @var ConstraintViolationInterface $constraintViolation */
        foreach ($constraintViolationList as $constraintViolation) {
            $errors[] = $constraintViolation->getMessage();
        }

        return $errors ?? [];
    }

    /**
     * REST PUT
     *
     * @param Request $request
     * @param string $type
     * @param int    $itemId Navigation item id
     *
     * @ApiDoc(
     *  description="Update Navigation item",
     *  resource=true
     * )
     * @return Response
     */
    public function putIdAction(Request $request, $type, $itemId)
    {
        $params = $request->request->all();

        if (empty($params)) {
            return $this->handleView(
                $this->view(
                    array('message' => 'Wrong JSON inside POST body'),
                    Codes::HTTP_BAD_REQUEST
                )
            );
        }

        /** @var $entity \Oro\Bundle\NavigationBundle\Entity\NavigationItemInterface */
        $entity = $this->getFactory()->findItem($type, (int) $itemId);

        if (!$entity) {
            return $this->handleView($this->view(array(), Codes::HTTP_NOT_FOUND));
        }

        if (!$this->validatePermissions($entity->getUser())) {
            return $this->handleView($this->view(array(), Codes::HTTP_FORBIDDEN));
        }

        if (isset($params['url']) && !empty($params['url'])) {
            $params['url'] = $this->getStateUrl($params['url']);
        }

        $entity->setValues($params);

        $em = $this->getManager();

        $em->persist($entity);
        $em->flush();

        return $this->handleView($this->view(array(), Codes::HTTP_OK));
    }

    /**
     * REST DELETE
     *
     * @param string $type
     * @param int    $itemId
     *
     * @ApiDoc(
     *  description="Remove Navigation item",
     *  resource=true
     * )
     * @return Response
     */
    public function deleteIdAction($type, $itemId)
    {
        /** @var $entity \Oro\Bundle\NavigationBundle\Entity\NavigationItemInterface */
        $entity = $this->getFactory()->findItem($type, (int) $itemId);
        if (!$entity) {
            return $this->handleView($this->view(array(), Codes::HTTP_NOT_FOUND));
        }
        if (!$this->validatePermissions($entity->getUser())) {
            return $this->handleView($this->view(array(), Codes::HTTP_FORBIDDEN));
        }

        $em = $this->getManager();
        $em->remove($entity);
        $em->flush();

        return $this->handleView($this->view(array(), Codes::HTTP_NO_CONTENT));
    }

    /**
     * Validate permissions on pinbar
     *
     * @param  AbstractUser $user
     * @return bool
     */
    protected function validatePermissions(AbstractUser $user)
    {
        return is_a($user, $this->getUserClass(), true) &&
            ($user->getId() === ($this->getUser() ? $this->getUser()->getId() : 0));
    }

    /**
     * Get entity Manager
     *
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    protected function getManager()
    {
        return $this->getDoctrine()->getManagerForClass($this->getPinbarTabClass());
    }

    /**
     * Get entity factory
     *
     * @return ItemFactory
     */
    protected function getFactory()
    {
        return $this->get('oro_navigation.item.factory');
    }

    /**
     * Check if navigation item has corresponding page state and return modified URL
     *
     * @param  string $url Original URL
     * @return string Modified URL
     */
    protected function getStateUrl($url)
    {
        $state = $this->getPageStateRepository()->findOneByPageId(base64_encode($url));

        return is_null($state)
            ? $url
            : $url . (strpos($url, '?') ? '&restore=1' : '?restore=1');
    }

    /**
     * Normalizes URL.
     *
     * @param string $url Original URL
     * @param string $type Navigation item type
     * @return string Normalized URL
     */
    private function normalizeUrl(string $url, string $type): string
    {
        /** @var PinbarTabUrlNormalizer $normalizer */
        $normalizer = $this->container->get('oro_navigation.utils.pinbar_tab_url_normalizer');

        // Adds "restore" GET parameter to URL if we are dealing with pinbar. Page state for pinned page is restored
        // only if this parameter is specified.
        if ($type === 'pinbar') {
            $urlInfo = parse_url($url);
            parse_str($urlInfo['query'] ?? '', $query);

            if (!isset($query['restore'])) {
                $query['restore'] = 1;
                $url = sprintf('%s?%s', $urlInfo['path'] ?? '', http_build_query($query));
            }
        }

        return $normalizer->getNormalizedUrl($url);
    }

    /**
     * @return ObjectRepository
     */
    protected function getPageStateRepository()
    {
        return $this->getDoctrine()->getRepository('OroNavigationBundle:PageState');
    }

    /**
     * @return string
     */
    protected function getPinbarTabClass()
    {
        return $this->getParameter('oro_navigation.entity.pinbar_tab.class');
    }

    /**
     * @return string
     */
    protected function getUserClass()
    {
        return $this->getParameter('oro_user.entity.class');
    }
}
