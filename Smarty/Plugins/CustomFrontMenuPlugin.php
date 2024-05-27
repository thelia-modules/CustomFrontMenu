<?php
namespace CustomFrontMenuPlugin\Smarty\Plugins;

use Thelia\Core\Translation\Translator;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use CustomFrontMenu\Controller\MenuController;
use CustomFrontMenu\CustomFrontMenu;

class CustomFrontMenuPlugin extends AbstractSmartyPlugin
{

    public function __construct(private MenuController $menuController)
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
            throw new \InvalidArgumentException(Translator::getInstance()->trans('The menu_id parameter is required', [], CustomFrontMenu::DOMAIN_NAME), 0);
        }

        $menuItems = $this->menuController->getMenuItems();
        $smarty->assign('menuItems', $menuItems);

        $templatePath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/customFrontMenu.html';
        $smarty->display($templatePath);
    }
}