<?php
namespace CustomFrontMenu\Smarty\Plugins;

use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Interface\CFMMenuInterface;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use Thelia\Core\HttpFoundation\Session\Session;

class CustomFrontMenuPlugin extends AbstractSmartyPlugin
{

    public function __construct(private CFMLoadInterface $CFMLoadService, private CFMMenuInterface $cfmMenu, private Session $session)
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

        $menuItems = $this->CFMLoadService->loadTableBrowserLang($menu, $this->session->get('thelia.current.lang')->getLocale());
        $smarty->assign('menuItems', $menuItems);
        

        $cssPath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/assets/css/customFrontMenu.css.html';
        $smarty->display($cssPath);
        $templatePath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/customFrontMenu.html';
        $smarty->display($templatePath);
    }
}