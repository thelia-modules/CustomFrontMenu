<?php

namespace CustomFrontMenu\Hook;

use CustomFrontMenu\Controller\MenuController;
use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use CustomFrontMenu\Service\CustomFrontMenuService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Translation\Translator;
use CustomFrontMenu\CustomFrontMenu;
use TheliaSmarty\Template\SmartyParser;

class ConfigHook extends BaseHook
{
    public function __construct(
        protected CustomFrontMenuLoadService $customFrontMenuLoadService,
        protected CustomFrontMenuService     $customFrontMenuService,
        protected MenuController             $menuController,
        SmartyParser                         $parser,
        AssetResolverInterface               $resolver,
        EventDispatcherInterface             $eventDispatcher)
    {
        parent::__construct($parser, $resolver, $eventDispatcher);

    }

    public static function getSubscribedHooks() :array
    {
        return [
            "module.config-js" => [
                [
                    "type" => "back",
                    "method" => "addMenuJs"
                ]
            ],
            "main.head-css" => [
                [
                    "type" => "back",
                    "method" => "addMenuCss"
                ]
            ],
            "module.configuration" => [
                [
                    "type" => "back",
                    "method" => "onModuleConfiguration"
                ]
            ]
        ];
    }

    public function addMenuJs(HookRenderEvent $event):void
    {
        $event->add($this->addJS("assets/js/main.js"));
    }

    public function addMenuCss(HookRenderEvent $event):void
    {
       $event->add($this->addCSS("assets/css/styles.css"));
    }

    /**
     * @throws PropelException
     */
    public function onModuleConfiguration(HookRenderEvent $event) : void
    {
        $session = $this->getRequest()->getSession();
        if (isset($_COOKIE['menuId']) && $_COOKIE['menuId'] != -1) {
            $data = $this->menuController->loadMenuItems($session, $this->customFrontMenuLoadService, $this->customFrontMenuService, $_COOKIE['menuId']);
        } else {
            $data = $this->menuController->loadMenuItems($session, $this->customFrontMenuLoadService, $this->customFrontMenuService);
        }

        $event->add($this->render("module-config.html", $data));
    }
}
