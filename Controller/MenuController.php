<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Lang;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

    #[Route("/admin/module/CustomFrontMenu/selectMenu", name:"admin.customfrontmenu.select.menu", methods:["POST"])]
    public function selectOtherMenu(Request $request) : RedirectResponse
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
            $this->loadMenuItems($this->getSession(), $menuId);
        } catch(\Exception $e) {
            $this->getSession()->getFlashBag()->add('fail', 'Fail to load this menu (3)');
        }


        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    #[Route("/admin/module/CustomFrontMenu/save", name:"admin.customfrontmenu.save", methods:["POST"])]
    public function saveMenuItems(Request $request) : RedirectResponse
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

        try {
            $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);
            $descendants = $menu->getDescendants();
            foreach ($descendants as $descendant) {
                $descendantI18n = CustomFrontMenuItemI18nQuery::create()->findById($descendant->getId());
                $descendantI18n->delete();
            }
            $menu->deleteDescendants();
            $menu->save();

            $this->saveTableBrowser($dataArray, $menu);

            $this->getSession()->getFlashBag()->add('success', 'This menu has been successfully saved !');

        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('fail', 'An error occurred when saving in database.');
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Save all elements from an array recursively to the database
     * @throws PropelException
     */
    private function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent) : void
    {
        foreach ($dataArray as $element) {
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent);
            $item->save();
            $content = new CustomFrontMenuItemI18n();
            $content->setTitle($element['title']);
            $content->setUrl($element['url']);
            $content->setId($item->getId());
            $content->setLocale('en_US');
            $content->save();
            if (isset($element['childrens']) && $element['childrens'] !== []) {
                $this->saveTableBrowser($element['childrens'], $item);
            }
        }
    }

    /**
     * Load all elements from the database recursively to parse them in an array
     * @throws PropelException
     */
    private function loadTableBrowser(array & $dataArray, CustomFrontMenuItem $parent) : void
    {
        $descendants = $parent->getChildren();
        foreach ($descendants as $descendant) {
            $newArray = [];
            $content = CustomFrontMenuItemI18nQuery::create()
                ->filterById($descendant->getId())
                ->findByLocale($descendant->getLocale());
            $newArray['depth'] = $descendant->getLevel() - 2;

            $newArray['title'] = $content->getColumnValues('title');
            $newArray['url'] = $content->getColumnValues('url');
            $newArray['id'] = $this->COUNT_ID;
            ++$this->COUNT_ID;

            if ($descendant->hasChildren()) {
                $newArrayChildren = [];
                $this->loadTableBrowser($newArrayChildren, $descendant);
                $newArray['childrens'] = $newArrayChildren;
            }
            $dataArray[] = $newArray;
        }
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

    #[Route("/admin/module/CustomFrontMenu/add", name:"admin.customfrontmenu.addmenu", methods:["POST"])]
    public function addMenu(Request $request) : RedirectResponse
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
            $this->getSession()->getFlashBag()->add('success', 'New menu added successfully');
        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('fail', 'Failed to add a new menu');
        }
        
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    #[Route("/admin/module/CustomFrontMenu/delete", name:"admin.customfrontmenu.deletemenu", methods:["POST"])]
    public function deleteMenu(Request $request) : RedirectResponse
    {

        $firstCurrentMenuId = $request->get('menuId');
        if($firstCurrentMenuId === null || $firstCurrentMenuId === 'menu-selected-') {
            $this->getSession()->getFlashBag()->add('fail', 'Fail to delete the current menu (1)');
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        $currentMenuId = intval(str_replace("menu-selected-", "", $firstCurrentMenuId));

        try {
            CustomFrontMenuItemI18nQuery::create()->findById($currentMenuId)->delete();
            CustomFrontMenuItemQuery::create()->findById($currentMenuId)->delete();
            $this->getSession()->getFlashBag()->add('success', 'Current menu deleted successfully');
        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('fail', 'Fail to delete the current menu (2)');
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
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
     * Clear all flashes
     */
    #[Route("/admin/module/CustomFrontMenu/clearFlashes", name:"admin.customfrontmenu.clearflashes", methods:["GET"])]
    public function clearFlashes() : void
    {
        $this->getSession()->getFlashBag()->clear();
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
            try {
                $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);
                if (isset($menu)) {
                    $this->loadTableBrowser($data, $menu);
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
    }
}