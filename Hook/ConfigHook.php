<?php

namespace CustomFrontMenu\Hook;

use CustomFrontMenu\Controller\MenuController;
use CustomFrontMenu\Service\CFMSaveService;
use CustomFrontMenu\Service\CFMLoadService;
use CustomFrontMenu\Service\CFMMenuService;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Translation\Translator;
use CustomFrontMenu\CustomFrontMenu;

class ConfigHook extends BaseHook
{
    protected $cfmSave, $cfmLoad, $cfmMenu, $menuController;

    public function __construct(CFMSaveService $cfmSave, CFMLoadService $cfmLoad, CFMMenuService $cfmMenu, MenuController $menuController)
    {
        parent::__construct();
        $this->cfmSave = $cfmSave;
        $this->cfmLoad = $cfmLoad;
        $this->cfmMenu = $cfmMenu;
        $this->menuController = $menuController;
    }

    public function onModuleConfiguration(HookRenderEvent $event) : void
    {
        $session = $this->getRequest()->getSession();
        try {
            if (isset($_COOKIE['menuId']) && $_COOKIE['menuId'] != -1) {
                $data = $this->menuController->loadMenuItems($session, $this->cfmLoad, $this->cfmMenu, $_COOKIE['menuId']);
            } else {
                $data = $this->menuController->loadMenuItems($session, $this->cfmLoad, $this->cfmMenu);
            }
        }
        catch (\Exception $e) {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to load this menu', [], CustomFrontMenu::DOMAIN_NAME));
        }

        $event->add($this->render("module-config.html", $data));
    }
}
