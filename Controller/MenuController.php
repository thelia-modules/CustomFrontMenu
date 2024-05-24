<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\Model\CustomFrontMenuContent;
use CustomFrontMenu\Model\CustomFrontMenuContentQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Service\CFMSaveService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\URL;

use CustomFrontMenu\Service\CFMLoadService;

class MenuController extends BaseAdminController
{
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
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));;
        }
        $dataJson = $request->get('menuData');
        $dataArray = json_decode($dataJson, true);

        $messages = [];

        try {
            CustomFrontMenuContentQuery::create()->deleteAll();
            CustomFrontMenuItemQuery::create()->deleteAll();

            $root = $this->getRoot();

            $cfmSave = new CFMSaveService();
            $cfmSave->saveTableBrowser($dataArray, $root);

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
     * Load the different menu names
     * @throws PropelException
     */
    public function loadSelectMenu() : array
    {
        $root = $this->getRoot();
        $descendants = $root->getChildren();
        foreach ($descendants as $descendant) {
            $content = CustomFrontMenuContentQuery::create()->findByMenuItem($descendant->getId());
            $dataArray[] = $content->getColumnValues('title')[0];
        }
        return $dataArray;
    }

    /**
     * 
     * @Route("/admin/module/CustomFrontMenu/add", name="admin.addmenu", methods={"POST"})
     */
    public function addMenu(Request $request) : RedirectResponse
    {
        $this->getSession()->getFlashBag()->add('fail', 'Not implemented yet');
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        // $menuName = $request->get('menuName');
        // $root = $this->getRoot();
        // $item = new CustomFrontMenuItem();
        // $item->insertAsLastChildOf($root);
        // $item->save();
        // $content = new CustomFrontMenuContent();
        // $content->setTitle($menuName);
        // $content->setMenuItem($item->getId());
        // $content->save();
    }

    /**
     * 
     * @Route("/admin/module/CustomFrontMenu/delete", name="admin.deletemenu", methods={"POST"})
     */
    public function deleteMenu(Request $request) : RedirectResponse
    {
        $this->getSession()->getFlashBag()->add('fail', 'Not implemented yet');
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
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
     * @return void
     */
    public function loadMenuItems() : void
    {
        $data = [];
        try {
            $root = $this->getRoot();

            $cfmLoad = new CFMLoadService();
            $data[] = $cfmLoad->loadTableBrowser($root);
        } catch (\Exception $e2) {
            //$this->getSession()->getFlashBag()->add('fail', 'Fail to load data from the database');

        }

        $menuNames = [];
        try {
            $menuNames = $this->loadSelectMenu();
        } catch (\Exception $e3) {
            //$this->getSession()->getFlashBag()->add('fail', 'Fail to load data from the database');

        }

        $namesToLoad = json_encode($menuNames);
        setcookie('menuNames', $namesToLoad);

        $dataToLoad = json_encode($data);
        setcookie('menuItems', $dataToLoad);
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
}