<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use CustomFrontMenu\Service\CustomFrontMenuSaveService;
use CustomFrontMenu\Service\CustomFrontMenuService;
use Exception;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

    /**
     * Load the menu selected by the user.
     * @param Request $request The user request with the desired menu id
     * @return RedirectResponse
     */
    #[Route("/admin/module/CustomFrontMenu/selectMenu", name: "admin.customfrontmenu.select.menu", methods: ["POST"])]
    public function selectOtherMenu(Request $request) : RedirectResponse
    {
        $menuId = intval(str_replace("menu-selected-", "", $request->get('menuId')));

        setcookie('menuId', $menuId);

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Save the selected menu items in database.
     * @param Request $request The user request with the menu items and the selected menu id
     * @param SessionInterface $session The user session, used to get locale and to display flashes
     * @param CustomFrontMenuSaveService $customFrontMenuSave The saving service
     * @param CustomFrontMenuService $customFrontMenuService
     * @throws PropelException
     * @throws Exception
     */
    #[Route("/admin/module/CustomFrontMenu/save", name:"admin.customfrontmenu.save", methods:["POST"])]
    public function saveMenuItems(Request $request, SessionInterface $session, CustomFrontMenuSaveService $customFrontMenuSave, CustomFrontMenuService $customFrontMenuService) : RedirectResponse
    {
        $dataJson = $request->get('menuData');
        $newMenu = json_decode($dataJson, true);
        $menuId = json_decode($request->get('menuDataId'));

        if (!isset($menuId) || $menuId === 'undefined' || $menuId === 'null') {
            throw new Exception('Save failed : the menu id cannot be null or empty');
        }

        $menuToCheck = $customFrontMenuService->getMenu($menuId);

        if (!isset($menuToCheck) || $menuToCheck->getLevel() !== 1) {
            throw new Exception('Save failed : the menu id is invalid');
        }

        // Delete all the items currently in database for the menu to save
        $menu = $customFrontMenuSave->deleteSpecificItems($menuId);

        // Add all new items in database
        $customFrontMenuSave->saveTableBrowser($newMenu, $menu, $session);

        $session->getFlashBag()->add('success', Translator::getInstance()->trans('This menu has been successfully saved !', [], CustomFrontMenu::DOMAIN_NAME));

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Add a new menu with the name given by the user.
     * The user is redirected in this new menu.
     * @param Request $request The user request with the menu name
     * @param SessionInterface $session The user session, used to display flashes
     * @param CustomFrontMenuLoadService $customFrontMenuLoadService The loading service
     * @param CustomFrontMenuService $customFrontMenuService The menu service
     * @throws Exception
     */
    #[Route("/admin/module/CustomFrontMenu/add", name: "admin.customfrontmenu.addmenu", methods: ["POST"])]
    public function addMenu(Request $request, SessionInterface $session, CustomFrontMenuLoadService $customFrontMenuLoadService, CustomFrontMenuService $customFrontMenuService) : RedirectResponse
    {
        $menuName = $request->get('menuName');
        $root = $customFrontMenuService->getRoot();
        $itemId = $customFrontMenuService->addMenu($root, $menuName, $session);
        $this->loadMenuItems($session, $customFrontMenuLoadService, $customFrontMenuService, $itemId);
        setcookie('menuId', $itemId);
        $session->getFlashBag()->add('success', Translator::getInstance()->trans('New menu added successfully', [], CustomFrontMenu::DOMAIN_NAME));

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Delete the current menu.
     * The user is redirected in the first menu if it exists.
     * @param Request $request The user request with the menu id
     * @param SessionInterface $session The user session, used to display flashes
     * @param CustomFrontMenuService $customFrontMenuService The menu service
     * @throws Exception
     */
    #[Route("/admin/module/CustomFrontMenu/delete", name:"admin.customfrontmenu.deletemenu", methods:["POST"])]
    public function deleteMenu(Request $request, SessionInterface $session, CustomFrontMenuService $customFrontMenuService) : RedirectResponse
    {
        $firstCurrentMenuId = $request->get('menuId');
        if($firstCurrentMenuId === null || $firstCurrentMenuId === 'menu-selected-') {
            throw new Exception('Delete failed : the menu id cannot be null or empty');
        }

        $currentMenuId = intval(str_replace("menu-selected-", "", $firstCurrentMenuId));

        $customFrontMenuService->deleteMenu($currentMenuId);
        $session->getFlashBag()->add('success', Translator::getInstance()->trans('Current menu deleted successfully', [], CustomFrontMenu::DOMAIN_NAME));

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
     * @param CustomFrontMenuLoadService $customFrontMenuLoadService The loading service
     * @param CustomFrontMenuService $customFrontMenuService The menu service
     * @param int|null $menuId The id of the menu to load
     * @return array All the data necessary to load the page content : Menu names,  menu items and the current menu id.
     * @throws PropelException
     */
    public function loadMenuItems(SessionInterface $session, CustomFrontMenuLoadService $customFrontMenuLoadService, CustomFrontMenuService $customFrontMenuService, ?int $menuId = null) : array
    {
        $menuNames = $customFrontMenuLoadService->loadSelectMenu($customFrontMenuService->getRoot());

        if (!isset($menuId)) {
            if(count($menuNames) > 0) {
                $menuId = intval(str_replace("menu-selected-", "", $menuNames[0]['id']));
            }
        }

        $data = [];
        if(isset($menuId)) {
            $menu = $customFrontMenuService->getMenu($menuId);
            if (!isset($menu) || $menu->getLevel() !== 1) {
                $session->getFlashBag()->add('fail', Translator::getInstance()->trans('This menu does not exists', [], CustomFrontMenu::DOMAIN_NAME));
                $menuId = intval(str_replace("menu-selected-", "", $menuNames[0]['id']));
                setcookie('menuId', $menuId, ['path' => '/admin/module/CustomFrontMenu']);
                $menu = $customFrontMenuService->getMenu($menuId);
            }

            $data = $customFrontMenuLoadService->loadTableBrowser($menu);
        }

        return [
            'menuNames' => json_encode($menuNames),
            'menuItems' => json_encode($data),
            'currentMenuId' => utf8_encode($menuId)
        ];
    }
}