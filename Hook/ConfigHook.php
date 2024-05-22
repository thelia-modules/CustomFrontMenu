<?php

namespace CustomFrontMenu\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use CustomFrontMenu\Controller\MenuController;
use Thelia\Core\HttpFoundation\Request;

class ConfigHook extends BaseHook
{
    public function onModuleConfiguration(HookRenderEvent $event) : void
    {

        $controller = new MenuController;

        $controller->loadMenuItems($this->getSession());

        $event->add($this->render("module-config.html"));
    }
}