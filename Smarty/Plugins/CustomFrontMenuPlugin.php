<?php
namespace CustomFrontMenu\Smarty\Plugins;

use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Interface\CFMMenuInterface;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class CustomFrontMenuPlugin extends AbstractSmartyPlugin
{

    public function __construct(private CFMLoadInterface $CFMLoadService, private CFMMenuInterface $cfmMenu)
    {
    }

    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor(
                'function',
                'CustomFrontMenuPlugin',
                $this,
                'renderCustomFrontMenuPlugin'
            ),
        ];
    }

    public function renderCustomFrontMenuPlugin($params, $smarty): void
    {
        if (!isset($params['menu_id'])) {
            throw new \InvalidArgumentException('The menu_id parameter is required', 1);
        }

        $menu = $this->cfmMenu->getMenu($params['menu_id']);
        if (!isset($menu)) {
            throw new \InvalidArgumentException('The menu does not exist', 2);
        }

        $menuItems = $this->CFMLoadService->loadTableBrowser($menu);
        $smarty->assign('menuItems', $menuItems);

        $templatePath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/customFrontMenu.html';
        $smarty->display($templatePath);
    }
}