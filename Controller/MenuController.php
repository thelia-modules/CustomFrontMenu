<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\Model\CustomFrontMenuContent;
use CustomFrontMenu\Model\CustomFrontMenuContentQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

    /**
     * @Route("/admin/module/CustomFrontMenu/selectMenu", name="admin.customfrontmenu.select.menu", methods={"POST"})
     */
    public function selectOtherMenu(Request $request) : RedirectResponse
    {
        print_r("test");
        if (null !== $this->checkAuth(
                AdminResources::MODULE,
                ['customfrontmenu'],
                AccessManager::UPDATE
            )) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }
        $menuId = $request->get('menuId');

        $messages = [];

        $this->loadMenuItems($this->getSession(), $menuId);

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * @Route("/admin/module/CustomFrontMenu/save", name="admin.responseform", methods={"POST"})
     */
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

        $messages = [];

        try {
            CustomFrontMenuContentQuery::create()->deleteAll();
            CustomFrontMenuItemQuery::create()->deleteAll();

            $root = $this->getRoot();

            $this->saveTableBrowser($dataArray, $root);

            $this->getSession()->getFlashBag()->add('success', 'This menu has been successfully saved !');

        } catch (\Exception $e) {
            $messages[] = $e->getMessage();
            // Uncomment this line to display error messages
            //$this->getSession()->getFlashBag()->add('fail', $e->getMessage());
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
            $content = new CustomFrontMenuContent();
            $content->setTitle($element['title']);
            $content->setUrl($element['url']);
            $content->setMenuItem($item->getId());
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
            $content = CustomFrontMenuContentQuery::create()->findByMenuItem($descendant->getId());
            $newArray['depth'] = $descendant->getLevel() - 1;

            $newArray['title'] = $content->getColumnValues('title')[0];
            $newArray['url'] = $content->getColumnValues('url')[0];
            $newArray['id'] = $this->COUNT_ID;
            ++$this->COUNT_ID;

            if ($descendant->hasChildren()) {
                $newArrayChildrens = [];
                $this->loadTableBrowser($newArrayChildrens, $descendant);
                $newArray['childrens'] = $newArrayChildrens;
            }
            $dataArray[] = $newArray;
        }
    }

     /**
     * Load the different menu names
      */
    public function loadSelectMenu() : array
    {
        $root = $this->getRoot();
        $descendants = $root->getChildren();
        $dataArray = [];
        foreach ($descendants as $descendant) {
            $newArray = [];
            $newArray['id'] = $descendant->getId();
            $content = CustomFrontMenuContentQuery::create()->findByMenuItem($descendant->getId());
            $newArray['title'] = $content->getColumnValues('title')[0];
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }

    /**
     * 
     * @Route("/admin/module/CustomFrontMenu/add", name="admin.addmenu", methods={"POST"})
     */
    public function addMenu(Request $request) : RedirectResponse
    {
        try {
            $menuName = $request->get('menuName');
            $root = $this->getRoot();
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($root);
            $item->save();
            $content = new CustomFrontMenuContent();
            $content->setTitle($menuName);
            $content->setMenuItem($item->getId());
            $content->save();
            $this->getSession()->getFlashBag()->add('success', 'New menu added successfully');
        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('fail', 'Failed to add a new menu');
        }
        
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * 
     * @Route("/admin/module/CustomFrontMenu/delete", name="admin.deletemenu", methods={"POST"})
     */
    public function deleteMenu(Request $request) : RedirectResponse
    {
        $currentMenuId = $request->get('menuId');
        if ($currentMenuId === null) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }
        try {
            CustomFrontMenuContentQuery::create()->findByMenuItem($currentMenuId)->delete();
            CustomFrontMenuItemQuery::create()->findById($currentMenuId)->delete();
            $this->getSession()->getFlashBag()->add('success', 'Current menu deleted successfully');
        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('fail', 'Failed to delete the current menu');
        }
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * Return the menu root from the database.
     * If this root doesn't exist, it's created.
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
     * @Route("/admin/module/CustomFrontMenu/clearFlashes", name="admin.clearflashes", methods={"GET"})
     */
    public function clearFlashes() : void
    {
        $this->getSession()->getFlashBag()->clear();
    }

    /**
     * Load the menu items
     */
    public function loadMenuItems(Session $session, int $menuId = null) : void
    {
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

        $menuNames = [];

        try {
            $menuNames = $this->loadSelectMenu();
        } catch (\Exception $e3) {
            $session->getFlashBag()->add('fail', 'Fail to load menu names from the database');

        }

        $namesToLoad = json_encode($menuNames);
        setcookie('menuNames', $namesToLoad);

        $dataToLoad = json_encode($data);
        setcookie('menuItems', $dataToLoad);

        setcookie('currentMenuId', $menuId);
    }
}