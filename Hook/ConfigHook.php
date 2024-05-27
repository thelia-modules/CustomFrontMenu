<?php

namespace CustomFrontMenu\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use CustomFrontMenu\Controller\MenuController;

class ConfigHook extends BaseHook
{
    public function onModuleConfiguration(HookRenderEvent $event) : void
    {
        $controller = new MenuController;

        if (isset($_COOKIE['menuId']) && $_COOKIE['menuId'] != -1) {
            $data = $controller->loadMenuItems($this->getSession(), $_COOKIE['menuId']);
        } else {
            echo "No menu selected";
            $data = $controller->loadMenuItems($this->getSession());
        }

        $event->add($this->render("module-config.html", $data));
    }
}