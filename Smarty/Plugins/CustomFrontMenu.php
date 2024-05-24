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
        $templatePath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/customFrontMenu.html';
        $smarty->display($templatePath);
    }
}