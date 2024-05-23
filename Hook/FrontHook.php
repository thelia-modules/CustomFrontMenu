<?php

namespace CustomFrontMenu\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use CustomFrontMenu\Controller\MenuController;

class FrontHook extends BaseHook
{
    public function renderMenu(HookRenderEvent $event){

        $ctrl = new MenuController;

        $event->add($this->render("customFrontMenu.html", ['menuItems' => $ctrl->getMenuItems()]));
    }
}