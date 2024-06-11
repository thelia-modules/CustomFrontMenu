<?php
namespace CustomFrontMenu\Smarty\Plugins;

use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use CustomFrontMenu\Service\CustomFrontMenuService;
use Symfony\Component\HttpFoundation\RequestStack;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class CustomFrontMenuPlugin extends AbstractSmartyPlugin
{

    public function __construct(
        private CustomFrontMenuLoadService $CustomFrontMenuLoadService,
        private CustomFrontMenuService $customFrontMenuService,
        private RequestStack $requestStack
    ) {
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
        $langAdmin = $this->requestStack->getCurrentRequest()->getSession()->getAdminLang()->getLocale();

        if (!$params['menu_id']) {
            throw new \InvalidArgumentException('The menu_id parameter is required', 1);
        }

        $menu = $this->customFrontMenuService->getMenu($params['menu_id']);
        if (!$menu) {
            throw new \InvalidArgumentException('The menu does not exist', 2);
        }

        $menuItems = $this->CustomFrontMenuLoadService->loadTableBrowserLang($menu, $langAdmin);
        $smarty->assign('menuItems', $menuItems);

        $cssPath = $smarty->getTemplateDir("CustomFrontMenu"). "assets/css/customFrontMenu.css.html";
        $smarty->display($cssPath);
        $templatePath = $smarty->getTemplateDir("CustomFrontMenu"). "customFrontMenu.html";
        $smarty->display($templatePath);
    }
}