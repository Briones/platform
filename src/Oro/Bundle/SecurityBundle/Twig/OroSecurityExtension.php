<?php

namespace Oro\Bundle\SecurityBundle\Twig;

use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Acl\Permission\PermissionManager;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\SecurityBundle\Entity\Permission;
use Oro\Bundle\SecurityBundle\Model\AclPermission;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Provides custom twig functions for security check purposes.
 */
class OroSecurityExtension extends \Twig_Extension
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return AuthorizationCheckerInterface
     */
    protected function getAuthorizationChecker()
    {
        return $this->container->get('security.authorization_checker');
    }

    /**
     * @return TokenAccessorInterface
     */
    protected function getTokenAccessor()
    {
        return $this->container->get('oro_security.token_accessor');
    }

    /**
     * @return PermissionManager
     */
    protected function getPermissionManager()
    {
        return $this->container->get('oro_security.acl.permission_manager');
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_enabled_organizations', [$this, 'getOrganizations']),
            new \Twig_SimpleFunction('get_current_organization', [$this, 'getCurrentOrganization']),
            new \Twig_SimpleFunction('acl_permission', [$this, 'getPermission']),
        ];
    }

    /**
     * Get list with all enabled organizations for current user
     *
     * @return Organization[]
     */
    public function getOrganizations()
    {
        $user = $this->getTokenAccessor()->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $user->getOrganizations(true)->toArray();
    }

    /**
     * Returns current organization
     *
     * @return Organization|null
     */
    public function getCurrentOrganization()
    {
        return $this->getTokenAccessor()->getOrganization();
    }

    /**
     * @param AclPermission $aclPermission
     *
     * @return Permission
     */
    public function getPermission(AclPermission $aclPermission)
    {
        return $this->getPermissionManager()->getPermissionByName($aclPermission->getName());
    }

    /**
     * Returns the name of the extension.
     *
     * @return string
     */
    public function getName()
    {
        return 'oro_security_extension';
    }
}
