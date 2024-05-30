<?php

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Interface\CFMSaveInterface;
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
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;
use CustomFrontMenu\Service\Validator;

use CustomFrontMenu\Service\CFMLoadService;
use CustomFrontMenu\Service\CFMSaveService;

class MenuController extends BaseAdminController
{
    private int $COUNT_ID = 1;

    #[Route("/admin/module/CustomFrontMenu/selectMenu", name: "admin.customfrontmenu.select.menu", methods: ["POST"])]
    public function selectOtherMenu(Request $request, CFMLoadService $cfmLoadService) : RedirectResponse
    {
        $menuId = intval(str_replace("menu-selected-", "", $request->get('menuId')));

        setcookie('menuId', $menuId);

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }


    #[Route("/admin/module/CustomFrontMenu/save", name:"admin.customfrontmenu.save", methods:["POST"])]
    public function saveMenuItems(Request $request, SessionInterface $session, CFMSaveInterface $cfmSaveService, CFMLoadInterface $cfmLoadService) : RedirectResponse
    {
        $dataJson = $request->get('menuData');
        $newMenu = json_decode($dataJson, true);
        $menuId = json_decode($request->get('menuDataId'));

        if (!isset($menuId) || $menuId === 'undefined' || $menuId === 'null') {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('An error occurred when saving in database. Cannot save if no menu is selected', [], CustomFrontMenu::DOMAIN_NAME));
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        $locale = $session->getAdminLang()->getLocale();

        $parent = CustomFrontMenuItemQuery::create()->findOneById($menuId);
        $oldMenu = $cfmLoadService->loadTableBrowser($parent);


        try {
            // Delete all the items currently in database for the menu to save
            $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);
            $menu->getParent();
            $descendants = $menu->getDescendants();
            foreach ($descendants as $descendant) {
                CustomFrontMenuItemI18nQuery::create()
                    ->findById($descendant->getId())
                    ->delete();
            }
            $menu->deleteDescendants();

            $locale = $session->get('_locale', 'en_US');

            $menu->save();

            // Add all new items in database
            $locale = $session->get('_locale', 'en_US');
            $cfmSaveService->saveTableBrowser($newMenu, $menu, $session, $locale);

            $session->getFlashBag()->add('success', Translator::getInstance()->trans('This title has been successfully saved !', [], CustomFrontMenu::DOMAIN_NAME));

        } catch (\Exception $e) {
            print_r($e->getMessage());
            die;
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('An error occurred when saving in database', [], CustomFrontMenu::DOMAIN_NAME));
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
                ->findByLocale("en_US");
            $newArray['title'] = $content->getColumnValues('title')[0];
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }

    #[Route("/admin/module/CustomFrontMenu/add", name: "admin.customfrontmenu.addmenu", methods: ["POST"])]
    public function addMenu(Request $request, SessionInterface $session, CFMLoadService $cfmLoadService) : RedirectResponse
    {
        try {
            $menuName = $request->get('menuName');
            $root = $this->getRoot();
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($root);
            $item->save();
            $content = new CustomFrontMenuItemI18n();
            $content->setTitle(Validator::completeValidation($menuName, $session));
            $content->setId($item->getId());
            $content->setLocale('en_US');
            $content->save();
            $locale = $session->get('_locale', 'en_US');
            $this->loadMenuItems($locale, $session, $cfmLoadService, $item->getId());
            setcookie('menuId', $item->getId());
            $session->getFlashBag()->add('success', Translator::getInstance()->trans('New menu added successfully', [], CustomFrontMenu::DOMAIN_NAME));
        } catch (\Exception $e) {
            dd($e->getMessage());
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Failed to add a new menu', [], CustomFrontMenu::DOMAIN_NAME));
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    #[Route("/admin/module/CustomFrontMenu/delete", name:"admin.customfrontmenu.deletemenu", methods:["POST"])]
    public function deleteMenu(Request $request, SessionInterface $session) : RedirectResponse
    {

        $firstCurrentMenuId = $request->get('menuId');
        if($firstCurrentMenuId === null || $firstCurrentMenuId === 'menu-selected-') {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to delete the current menu', [], CustomFrontMenu::DOMAIN_NAME));
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
        }

        $currentMenuId = intval(str_replace("menu-selected-", "", $firstCurrentMenuId));

        try {
            CustomFrontMenuItemI18nQuery::create()->findById($currentMenuId)->delete();
            CustomFrontMenuItemQuery::create()->findById($currentMenuId)->delete();
            $session->getFlashBag()->add('success', Translator::getInstance()->trans('Current menu deleted successfully', [], CustomFrontMenu::DOMAIN_NAME));
        } catch (\Exception $e) {
            $session->getFlashBag()->add('fail', Translator::getInstance()->trans('Fail to delete the current menu', [], CustomFrontMenu::DOMAIN_NAME));
        }

        if (isset($_COOKIE['menuId'])) {
            setcookie('menuId', -1);
        }

        if (isset($_COOKIE['menuId'])) {
            setcookie('menuId', -1);
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

    public function loadMenuItems(SessionInterface $session, CFMLoadInterface $cfmLoadService, ?int $menuId = null) : array
    {
        $menuNames = [];
        try {
            $menuNames = $this->loadSelectMenu();
        } catch (\Exception $e3) {
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
                $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);
                if (isset($menu)) {
                    $cfmLoadService = new CFMLoadService();
                    $data = $cfmLoadService->loadTableBrowser($menu);
                } else {
                    $session->getFlashBag()->add('fail', Translator::getInstance()->trans('This menu does not exists', [], CustomFrontMenu::DOMAIN_NAME));
                    setcookie('menuId', -1);
                }

            } catch (\Exception $e2) {
                dd($e2->getMessage());
                $session->getFlashBag()->add('fail', 'Fail to load data from the database');
            }
        }

        return [
            'menuNames' => json_encode($menuNames),
            'menuItems' => json_encode($data),
            'currentMenuId' => utf8_encode($menuId)
        ];
    }


    /**
     * Get the list of all menu items
     * @return array
     */
    public function getMenuItems(SessionInterface $session, CFMLoadInterface $cfmLoadService) : array
    {
        $data = [];

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
}