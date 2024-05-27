<?php

namespace CustomFrontMenu\Hook;

use CustomFrontMenu\Controller\MenuController;
use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Interface\CFMLoadInterface;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Event\Hook\HookRenderEvent;

class ConfigHook extends BaseHook
{
    protected $cfmSave, $cfmLoad, $menuController;

    public function __construct(CFMSaveInterface $cfmSave, CFMLoadInterface $cfmLoad, MenuController $menuController)
    {
        parent::__construct();
        $this->cfmSave = $cfmSave;
        $this->cfmLoad = $cfmLoad;
        $this->menuController = $menuController;
    }

    public function onModuleConfiguration(HookRenderEvent $event) : void
    {
        $controller = new MenuController;

        if (isset($_COOKIE['menuId']) && $_COOKIE['menuId'] != -1) {
            $data = $controller->loadMenuItems($this->getSession(), $_COOKIE['menuId']);
        } else {
            $data = $controller->loadMenuItems($this->getSession());
        }

        $event->add($this->render("module-config.html", $data));
    }
}
