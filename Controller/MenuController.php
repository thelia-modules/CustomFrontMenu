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
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

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

            if (CustomFrontMenuItemQuery::create()->findRoot() === null) {
                $root = new CustomFrontMenuItem();
                $root->makeRoot();
                $root->save();
            } else {
                $root = CustomFrontMenuItemQuery::create()->findRoot();
            }


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
            $root = CustomFrontMenuItemQuery::create()->findRoot();

            $this->loadTableBrowser($data, $root);
        } catch (\Exception $e2) {
            $this->getSession()->getFlashBag()->add('fail', 'Fail to load data from the database');

        }

        $dataToLoad = json_encode($data);

        setcookie('menuItems', $dataToLoad);
    }
}