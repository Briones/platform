<?php

namespace Oro\Bundle\ApiBundle\EventListener;

use Oro\Bundle\SecurityBundle\Http\Firewall\ExceptionListener;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prevents usage of Session in case if the request does not have session identifier in cookies.
 * This is required because API can work in two modes, stateless and statefull.
 * The statefull mode is used when API is called internally from web pages as AJAX request.
 */
class SecurityFirewallExceptionListener extends ExceptionListener
{
    /**
     * {@inheritdoc}
     */
    protected function setTargetPath(Request $request): void
    {
        $session = $request->getSession();
        if (null !== $session && $request->cookies->has($session->getName())) {
            parent::setTargetPath($request);
        }
    }
}
