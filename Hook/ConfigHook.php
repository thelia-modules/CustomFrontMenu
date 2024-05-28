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
        $session = $this->getRequest()->getSession();
        $locale = $session->getAdminLang()->getLocale();
        if (isset($_COOKIE['menuId']) && $_COOKIE['menuId'] != -1) {
            $data = $this->menuController->loadMenuItems($locale, $session, $_COOKIE['menuId']);
        } else {
            $data = $this->menuController->loadMenuItems($locale, $session);
        }

        $event->add($this->render("module-config.html", $data));
    }
}
