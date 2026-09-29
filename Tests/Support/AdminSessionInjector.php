<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/
namespace CustomFrontMenu\Tests\Support;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\Admin;

/**
 * Puts an administrator in the session of every request the test client sends, between
 * the session start and the admin firewall, as a login would have left it.
 */
final class AdminSessionInjector implements EventSubscriberInterface
{
    private ?Admin $admin = null;

    public function setAdmin(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->admin = $admin;
    }

    public function clear(): void
    {
        $this->admin = null;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $this->admin || !$event->getRequest()->hasSession(true)) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set('thelia.admin_user', $this->admin);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 200]];
    }
}
