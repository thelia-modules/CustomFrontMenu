<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\Model\CustomFrontMenuContentQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\URL;

use CustomFrontMenu\Service\CFMLoadService;
use CustomFrontMenu\Service\CFMSaveService;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

    #[Route("/admin/module/CustomFrontMenu/selectMenu", name: "admin.customfrontmenu.select.menu", methods: ["POST"])]
    public function selectOtherMenu(Request $request, Session $session) : RedirectResponse
    {
        if (null !== $this->checkAuth(
                AdminResources::MODULE,
                ['customfrontmenu'],
                AccessManager::UPDATE
            )) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        $menuId = intval(str_replace("menu-selected-", "", $request->get('menuId')));

        try {
            $this->loadMenuItems($session, $menuId);
        } catch(\Exception $e) {
            $session->getFlashBag()->add('fail', 'Fail to load this menu (3)');
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }


    #[Route("/admin/module/CustomFrontMenu/save", name:"admin.customfrontmenu.save", methods:["POST"])]
    public function saveMenuItems(Request $request, SessionInterface $session) : RedirectResponse
    {
        if (null !== $this->checkAuth(
            AdminResources::MODULE,
            ['customfrontmenu'],
            AccessManager::UPDATE
        )) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }
        $dataJson = $request->get('menuData');
        $dataArray = json_decode($dataJson, true);
        $menuId = json_decode($request->get('menuDataId'));

        if (!isset($menuId) || $menuId === 'undefined' || $menuId === 'null') {
            $this->getSession()->getFlashBag()->add('fail', 'An error occurred when saving in database. Cannot save if no menu is selected.');
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        try {
            // Delete all the items currently in database for the menu to save
            $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);
            $menu->getParent();
            $descendants = $menu->getDescendants();
            foreach ($descendants as $descendant) {
                CustomFrontMenuItemI18nQuery::create()->findById($descendant->getId())->delete();
            }
            $menu->deleteDescendants();

            $menu->save();

            // Add all new items in database
            $cfmSaveService = new CFMSaveService();
            $cfmSaveService->saveTableBrowser($dataArray, $menu);

            $session->getFlashBag()->add('success', 'This menu has been successfully saved !');

        } catch (\Exception $e) {
            print_r($e->getMessage());
            $session->getFlashBag()->add('fail', 'An error occurred when saving in database.');
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Load the different menu names
     * @throws PropelException
     */
    public function loadSelectMenu() : array
    {

        $root = $this->getRoot();
        $descendants = $root->getChildren();
        $dataArray = [];
        foreach ($descendants as $descendant) {
            $newArray = [];
            $newArray['id'] = 'menu-selected-'.$descendant->getId();
            $content = CustomFrontMenuItemI18nQuery::create()
                ->filterById($descendant->getId())
                ->findByLocale($descendant->getLocale());

            $newArray['title'] = $content->getColumnValues('title');
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }

    #[Route("/admin/module/CustomFrontMenu/add", name: "admin.customfrontmenu.addmenu", methods: ["POST"])]
    public function addMenu(Request $request, SessionInterface $session) : RedirectResponse
    {
        try {
            $menuName = $request->get('menuName');
            $root = $this->getRoot();
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($root);
            $item->save();
            $content = new CustomFrontMenuItemI18n();
            $content->setTitle($menuName);
            $content->setId($item->getId());
            $content->setLocale('en_US');
            $content->save();
            $session->getFlashBag()->add('success', 'New menu added successfully');
        } catch (\Exception $e) {
            $session->getFlashBag()->add('fail', 'Failed to add a new menu');
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    #[Route("/admin/module/CustomFrontMenu/delete", name:"admin.customfrontmenu.deletemenu", methods:["POST"])]
    public function deleteMenu(Request $request, SessionInterface $session) : RedirectResponse
    {

        $firstCurrentMenuId = $request->get('menuId');
        if($firstCurrentMenuId === null || $firstCurrentMenuId === 'menu-selected-') {
            $session->getFlashBag()->add('fail', 'Fail to delete the current menu (1)');
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        $currentMenuId = intval(str_replace("menu-selected-", "", $firstCurrentMenuId));

        try {
            CustomFrontMenuItemI18nQuery::create()->findById($currentMenuId)->delete();
            CustomFrontMenuItemQuery::create()->findById($currentMenuId)->delete();
            $session->getFlashBag()->add('success', 'Current menu deleted successfully');
        } catch (\Exception $e) {
            $session->getFlashBag()->add('fail', 'Fail to delete the current menu (2)');
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Clear all flashes
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
     */
    public function loadMenuItems(Session $session, int $menuId = null) : void
    {
        $menuNames = [];
        try {
            $menuNames = $this->loadSelectMenu();
        } catch (\Exception $e3) {
            $session->getFlashBag()->add('fail', 'Fail to load menu names from the database');
        }

        if (!isset($menuId)) {
            if(count($menuNames) > 0) {
                $menuId = intval(str_replace("menu-selected-", "", $menuNames[0]['id']));
            }
        }

        $data = [];
        if(isset($menuId)) {
            $data = [];
            try {
                $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);
                if (isset($menu)) {
                    $cfmLoadService = new CFMLoadService();
                    $data = $cfmLoadService->loadTableBrowser($menu);
                } else {
                    $session->getFlashBag()->add('fail', "This menu doesn't exist");
                }
                
            } catch (\Exception $e2) {
                $session->getFlashBag()->add('fail', 'Fail to load data from the database');

            }
        }

        $namesToLoad = json_encode($menuNames);
        setcookie('menuNames', $namesToLoad);

        $dataToLoad = json_encode($data);
        setcookie('menuItems', $dataToLoad);

        setcookie('currentMenuId', $menuId);
        setcookie('currentMenuId', $menuId);
    }


    /**
     * Return the menu root from the database.
     * If this root doesn't exist, it's created.
     * @throws PropelException
     */
    private function getRoot() : CustomFrontMenuItem
    {
        if (CustomFrontMenuItemQuery::create()->findRoot() === null) {
            $root = new CustomFrontMenuItem();
            $root->makeRoot();
            $root->save();
        } else {
            $root = CustomFrontMenuItemQuery::create()->findRoot();
        }
        return $root;
    }

     /**
     * Get the list of all menu items
     * @return array
     */
    public function getMenuItems(SessionInterface $session) : array
    {
        $data = [];
        $cfmLoadService = new CFMLoadService();

        try {
            if (CustomFrontMenuItemQuery::create()->findRoot() === null) {
                $root = new CustomFrontMenuItem();
                $root->makeRoot();
                $root->save();
            } else {
                $root = CustomFrontMenuItemQuery::create()->findRoot();
            }
            $data = $cfmLoadService->loadTableBrowser($root);
        } catch (\Exception $e2) {
            $session->getFlashBag()->add('fail', 'Fail to load data from the database');
        }

        return $data;
    }
}