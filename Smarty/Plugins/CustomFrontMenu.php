<?php

namespace CustomFrontMenu\Smarty\Plugins;

use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use CustomFrontMenu\Controller\MenuController;

class CustomFrontMenu extends AbstractSmartyPlugin
{
    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor(
                'function',
                'CustomFrontMenu',
                $this,
                'CustomFrontMenu'
            ),
        ];
    }
    
    public function CustomFrontMenu($params): void // dependency injection of the custom front menu service
    {
        if (!isset($params['menu_id'])) {
            throw new \InvalidArgumentException('The menu_id parameter is required');
        }
        // $menu = $customFrontMenuService->getMenu($params['menu_id']); // Waiting for the custom front menu service
        $ctrl = new MenuController;

        $this->render("customFrontMenu.html", ['menuItems' => $ctrl->getMenuItems()]);
    }
}