<?php
namespace CustomFrontMenu\Smarty\Plugins;

use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use CustomFrontMenu\Service\CustomFrontMenuService;
use Thelia\Core\Event\TheliaEvents;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use Thelia\Core\HttpFoundation\Session\Session;

class CustomFrontMenuPlugin extends AbstractSmartyPlugin
{

    public function __construct(
        private CustomFrontMenuLoadService $CustomFrontMenuLoadService,
        private CustomFrontMenuService $customFrontMenuService,
        private Session $session)
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

        $menu = $this->customFrontMenuService->getMenu($params['menu_id']);
        if (!isset($menu)) {
            throw new \InvalidArgumentException('The menu does not exist', 2);
        }

        $menuItems = $this->CustomFrontMenuLoadService->loadTableBrowserLang($menu, $this->session->get('thelia.current.lang')->getLocale());
        $smarty->assign('menuItems', $menuItems);

        /*
         * issue d'un autre module
        $$this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);
        $imagePath = $event->getFileUrl();
        */
        $cssPath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/assets/css/customFrontMenu.css.html';
        $smarty->display($cssPath);
        $templatePath = THELIA_LOCAL_DIR . '/modules/CustomFrontMenu/templates/frontOffice/default/customFrontMenu.html';
        $smarty->display($templatePath);

    }
}