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

namespace CustomFrontMenu\Hook;

use CustomFrontMenu\Controller\MenuController;
use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use CustomFrontMenu\Service\CustomFrontMenuService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

class ConfigHook extends BaseHook
{
    public function __construct(
        private readonly CustomFrontMenuLoadService $customFrontMenuLoadService,
        private readonly CustomFrontMenuService $customFrontMenuService,
        private readonly MenuController $menuController,
        private readonly RequestStack $requestStack,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.config-js' => [
                ['type' => 'back', 'method' => 'addMenuJs'],
            ],
            'main.head-css' => [
                ['type' => 'back', 'method' => 'addMenuCss'],
            ],
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    public function addMenuJs(HookRenderEvent $event): void
    {
        $event->add($this->addJS('assets/js/main.js'));
    }

    public function addMenuCss(HookRenderEvent $event): void
    {
        $event->add($this->addCSS('assets/css/styles.css'));
    }

    /**
     * @throws PropelException
     */
    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add($this->render('module-config.html.twig', $this->menuController->loadMenuItems(
            $this->customFrontMenuLoadService,
            $this->customFrontMenuService,
            $this->selectedMenuId(),
        )));
    }

    /**
     * The screen remembers the menu being composed in a cookie, so it survives a redirect
     * after save. The value is client-controlled: anything but a positive integer means
     * "no selection" and lets the screen fall back to the first menu.
     */
    private function selectedMenuId(): ?int
    {
        $raw = $this->requestStack->getCurrentRequest()?->cookies->get('menuId');

        if (!\is_string($raw) || 1 !== preg_match('/^\d+$/', $raw)) {
            return null;
        }

        $menuId = (int) $raw;

        return $menuId > 0 ? $menuId : null;
    }
}
