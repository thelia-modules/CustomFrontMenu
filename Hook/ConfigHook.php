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
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * Renders the list of menus into the module configuration page.
 *
 * Composing a menu happens on the module's own pages, which are full back-office pages
 * extending the theme layout: this hook no longer injects any script or stylesheet, since
 * the screen is built from the theme's own components.
 */
class ConfigHook extends BaseHook
{
    public function __construct(
        private readonly MenuController $menuController,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    /**
     * @throws PropelException
     */
    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add($this->render('module-config.html.twig', $this->menuController->menuListData()));
    }
}
