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

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $this->menuController->loadMenuItems();
        $event->add($this->render("module-config.html"));
    }
}
