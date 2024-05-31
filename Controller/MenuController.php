<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Interface\CFMMenuInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;
use CustomFrontMenu\Service\Validator;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

    /**
     * Load the menu selected by the user.
     * @param Request $request The user request with the desired menu id
     * @param SessionInterface $session The user session, used to get locale and to display flashes
     * @param CFMLoadInterface $cfmLoad The loading service
     * @param CFMMenuInterface $cfmMenu The menu service
     */
    #[Route("/admin/module/CustomFrontMenu/selectMenu", name: "admin.customfrontmenu.select.menu", methods: ["POST"])]
    public function selectOtherMenu(Request $request, SessionInterface $session, CFMLoadInterface $cfmLoad, CFMMenuInterface $cfmMenu) : RedirectResponse
    {
        $menuId = intval(str_replace("menu-selected-", "", $request->get('menuId')));

        try {
            $locale = $session->get('_locale', 'en_US');
            $this->loadMenuItems($locale, $session, $cfmLoad, $cfmMenu, $menuId);
        } catch(\Exception $e) {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to load this menu (3)', [], CustomFrontMenu::DOMAIN_NAME));
        }

        setcookie('menuId', $menuId);

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Save the selected menu items in database.
     * @param Request $request The user request with the menu items and the selected menu id
     * @param SessionInterface $session The user session, used to get locale and to display flashes
     * @param CFMSaveInterface $cfmSave The saving service
     */
    #[Route("/admin/module/CustomFrontMenu/save", name:"admin.customfrontmenu.save", methods:["POST"])]
    public function saveMenuItems(Request $request, SessionInterface $session, CFMSaveInterface $cfmSave) : RedirectResponse
    {
        $dataJson = $request->get('menuData');
        $dataArray = json_decode($dataJson, true);
        $menuId = json_decode($request->get('menuDataId'));

        if (!isset($menuId) || $menuId === 'undefined' || $menuId === 'null') {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('An error occurred when saving in database. Cannot save if no menu is selected', [], CustomFrontMenu::DOMAIN_NAME));
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        try {
            // Delete all the items currently in database for the menu to save
            $menu = $cfmSave->deleteSpecificItems($menuId);

            // Add all new items in database
            $locale = $session->get('_locale', 'en_US');
            $cfmSave->saveTableBrowser($dataArray, $menu, $session, $locale);

            $session->getFlashBag()->add('success', Translator::getInstance()->trans('This menu has been successfully saved !', [], CustomFrontMenu::DOMAIN_NAME));

        } catch (\Exception $e) {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('An error occurred when saving in database', [], CustomFrontMenu::DOMAIN_NAME));
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Add a new menu with the name given by the user.
     * The user is redirected in this new menu.
     * @param Request $request The user request with the menu name
     * @param SessionInterface $session The user session, used to display flashes
     * @param CFMLoadInterface $cfmLoad The loading service
     * @param CFMMenuInterface $cfmMenu The menu service
     */
    #[Route("/admin/module/CustomFrontMenu/add", name: "admin.customfrontmenu.addmenu", methods: ["POST"])]
    public function addMenu(Request $request, SessionInterface $session, CFMLoadInterface $cfmLoad, CFMMenuInterface $cfmMenu) : RedirectResponse
    {
        try {
            $menuName = $request->get('menuName');
            $root = $cfmMenu->getRoot();
            $itemId = $cfmMenu->addMenu($root, $menuName, $session);
            $locale = $session->get('_locale', 'en_US');
            $this->loadMenuItems($locale, $session, $cfmLoad, $cfmMenu, $itemId);
            setcookie('menuId', $itemId);
            $session->getFlashBag()->add('success', Translator::getInstance()->trans('New menu added successfully', [], CustomFrontMenu::DOMAIN_NAME));
        } catch (\Exception $e) {
            dd($e->getMessage());
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Failed to add a new menu', [], CustomFrontMenu::DOMAIN_NAME));
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Delete the current menu.
     * The user is redirected in the first menu if it exists.
     * @param Request $request The user request with the menu id
     * @param SessionInterface $session The user session, used to display flashes
     * @param CFMMenuInterface $cfmMenu The menu service
     */
    #[Route("/admin/module/CustomFrontMenu/delete", name:"admin.customfrontmenu.deletemenu", methods:["POST"])]
    public function deleteMenu(Request $request, SessionInterface $session, CFMMenuInterface $cfmMenu) : RedirectResponse
    {

        $firstCurrentMenuId = $request->get('menuId');
        if($firstCurrentMenuId === null || $firstCurrentMenuId === 'menu-selected-') {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to delete the current menu (1)', [], CustomFrontMenu::DOMAIN_NAME));
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        $currentMenuId = intval(str_replace("menu-selected-", "", $firstCurrentMenuId));

        try {
            $cfmMenu->deleteMenu($currentMenuId);
            $session->getFlashBag()->add('success', Translator::getInstance()->trans('Current menu deleted successfully', [], CustomFrontMenu::DOMAIN_NAME));
        } catch (\Exception $e) {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to delete the current menu (2)', [], CustomFrontMenu::DOMAIN_NAME));
        }

        if (isset($_COOKIE['menuId'])) {
            setcookie('menuId', -1);
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Clear all flashes
     * @param SessionInterface $session The user session, used to manage flashes
     */
    #[Route("/admin/module/CustomFrontMenu/clearFlashes", name:"admin.customfrontmenu.clearflashes", methods:["GET"])]
    public function clearFlashes(SessionInterface $session) : Response
    {
        $session->getFlashBag()->clear();
        // Clear the response too to limit the data returned by http
        return new Response('', ResponseAlias::HTTP_OK);
    }

    /**
     * Load the menu items
     * @param SessionInterface $session The user session, used to get locale and to display flashes
     * @param CFMLoadInterface $cfmLoad The loading service
     * @param CFMMenuInterface $cfmMenu The menu service
     * @param int|null $menuId The id of the menu to load
     * @return array All the data necessary to load the page content : Menu names,  menu items and the current menu id.
     */
    public function loadMenuItems(string $locale, SessionInterface $session, CFMLoadInterface $cfmLoad, CFMMenuInterface $cfmMenu, ?int $menuId = null) : array
    {
        $menuNames = [];
        try {
            $root = $cfmMenu->getRoot();
            $menuNames = $cfmLoad->loadSelectMenu($root, $locale);
        } catch (\Exception $e3) {
            dd($e3->getMessage());
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to load menu names from the database', [], CustomFrontMenu::DOMAIN_NAME));
        }

        if (!isset($menuId)) {
            if(count($menuNames) > 0) {
                $menuId = intval(str_replace("menu-selected-", "", $menuNames[0]['id']));
            }
        }

        $data = [];
        if(isset($menuId)) {
            try {
                $menu = $cfmMenu->getMenu($menuId);
                if (isset($menu)) {
                    $data = $cfmLoad->loadTableBrowser($menu);
                } else {
                    $session->getFlashBag()->add('fail', Translator::getInstance()->trans('This menu does not exists', [], CustomFrontMenu::DOMAIN_NAME));
                    setcookie('menuId', -1);
                }

            } catch (\Exception $e2) {
                $session->getFlashBag()->add('fail', 'Fail to load data from the database');

            }
        }

        return [
            'menuNames' => json_encode($menuNames),
            'menuItems' => json_encode($data),
            'currentMenuId' => utf8_encode($menuId)
        ];
    }
}