<?php

namespace CustomFrontMenu\Smarty\Plugins;

use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use CustomFrontMenu\Controller\MenuController;

class CustomFrontMenu extends AbstractSmartyPlugin
{
    /*
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
    */

    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor(
                'function',
                'CustomFrontMenu',
                $this,
                'renderCustomFrontMenu'
            ),
        ];
    }

    public function renderCustomFrontMenu($params, $smarty): void
    {
        if (!isset($params['menu_id'])) {
            throw new \InvalidArgumentException('The menu_id parameter is required', 0);
        }

        $ctrl = new MenuController();

        $menuItems = $ctrl->getMenuItems();
        $smarty->assign('menuItems', $menuItems);
        //change by the racine of the project
        $templatePath = "/Users/nsillard/Sites/thelia-modules/local/modules/CustomFrontMenu/templates/frontOffice/default/customFrontMenu.html";
        $smarty->display($templatePath);
    }
}