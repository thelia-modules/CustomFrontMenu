<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

namespace CustomFrontMenu\Controller;

use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Service\BackOffice\MenuTargetCatalog;
use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use CustomFrontMenu\Service\CustomFrontMenuSaveService;
use CustomFrontMenu\Service\CustomFrontMenuService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    private const COOKIE_NAME = 'menuId';
    private const COOKIE_PATH = '/admin/module/CustomFrontMenu';

    public function __construct(
        protected readonly MenuTargetCatalog $targetCatalog,
    ) {
    }

    /**
     * BaseController types getSession() as SessionInterface, but flashes and the admin
     * language live on the Thelia session.
     */
    private function theliaSession(): Session
    {
        /** @var Session $session */
        $session = $this->getSession();

        return $session;
    }

    /**
     * Composing a menu is an administration operation: it needs the module resource,
     * and each POST carries the one-shot token.
     */
    private function denyUnlessAllowed(Request $request): ?Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFrontMenu', AccessManager::UPDATE)) {
            return $response;
        }

        $this->getTokenProvider()->checkToken((string) $request->request->get('_token', ''));

        return null;
    }

    private function backToScreen(): RedirectResponse
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl(self::COOKIE_PATH));
    }

    private function rememberMenu(int $menuId): void
    {
        setcookie(self::COOKIE_NAME, (string) $menuId, [
            'path' => self::COOKIE_PATH,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Load the menu selected by the user.
     */
    #[Route('/admin/module/CustomFrontMenu/selectMenu', name: 'admin.customfrontmenu.select.menu', methods: ['POST'])]
    public function selectOtherMenu(Request $request): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $this->rememberMenu((int) str_replace('menu-selected-', '', (string) $request->get('menuId')));

        return $this->backToScreen();
    }

    /**
     * Save the selected menu items in database.
     *
     * @throws PropelException
     */
    #[Route('/admin/module/CustomFrontMenu/save', name: 'admin.customfrontmenu.save', methods: ['POST'])]
    public function saveMenuItems(
        Request $request,
        CustomFrontMenuSaveService $customFrontMenuSave,
        CustomFrontMenuService $customFrontMenuService,
    ): Response {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $newMenu = json_decode((string) $request->get('menuData'), true);
        $menuId = json_decode((string) $request->get('menuDataId'));

        if (!\is_array($newMenu)) {
            throw new \InvalidArgumentException('Save failed: the menu payload is not a list of items');
        }

        if (!\is_int($menuId) || 0 === $menuId) {
            throw new \InvalidArgumentException('Save failed: the menu id cannot be null or empty');
        }

        $menuToCheck = $customFrontMenuService->getMenu($menuId);

        if (!$menuToCheck || 1 !== $menuToCheck->getLevel()) {
            throw new \InvalidArgumentException('Save failed: the menu id is invalid');
        }

        // Delete all the items currently in database for the menu to save
        $menu = $customFrontMenuSave->deleteSpecificItems($menuId);

        // Add all new items in database
        $customFrontMenuSave->saveTableBrowser($newMenu, $menu);

        $this->theliaSession()->getFlashBag()->add(
            'success',
            Translator::getInstance()->trans('This menu has been successfully saved !', [], CustomFrontMenu::DOMAIN_NAME),
        );

        return $this->backToScreen();
    }

    /**
     * Add a new menu with the name given by the user, and select it.
     *
     * @throws PropelException
     */
    #[Route('/admin/module/CustomFrontMenu/add', name: 'admin.customfrontmenu.addmenu', methods: ['POST'])]
    public function addMenu(Request $request, CustomFrontMenuService $customFrontMenuService): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $root = $customFrontMenuService->getRoot();
        $itemId = $customFrontMenuService->addMenu($root, (string) $request->get('menuName'));

        $this->rememberMenu($itemId);

        $this->theliaSession()->getFlashBag()->add(
            'success',
            Translator::getInstance()->trans('New menu added successfully', [], CustomFrontMenu::DOMAIN_NAME),
        );

        return $this->backToScreen();
    }

    /**
     * Delete the current menu.
     */
    #[Route('/admin/module/CustomFrontMenu/delete', name: 'admin.customfrontmenu.deletemenu', methods: ['POST'])]
    public function deleteMenu(Request $request, CustomFrontMenuService $customFrontMenuService): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $rawMenuId = (string) $request->get('menuId', '');

        if ('' === $rawMenuId || 'menu-selected-' === $rawMenuId) {
            throw new \InvalidArgumentException('Delete failed: the menu id cannot be null or empty');
        }

        $customFrontMenuService->deleteMenu((int) str_replace('menu-selected-', '', $rawMenuId));

        $this->theliaSession()->getFlashBag()->add(
            'success',
            Translator::getInstance()->trans('Current menu deleted successfully', [], CustomFrontMenu::DOMAIN_NAME),
        );

        $this->rememberMenu(-1);

        return $this->backToScreen();
    }

    /**
     * Everything the composition screen needs to render.
     *
     * @return array<string, mixed>
     *
     * @throws PropelException
     */
    public function loadMenuItems(
        CustomFrontMenuLoadService $customFrontMenuLoadService,
        CustomFrontMenuService $customFrontMenuService,
        ?int $menuId = null,
    ): array {
        $menuNames = $customFrontMenuLoadService->loadSelectMenu($customFrontMenuService->getRoot());
        $data = [];

        if (!$menuId && \count($menuNames) > 0) {
            $menuId = (int) str_replace('menu-selected-', '', $menuNames[0]['id']);
        }

        if ($menuId) {
            $menu = $customFrontMenuService->getMenu($menuId);

            if (!$menu || 1 !== $menu->getLevel()) {
                $this->theliaSession()->getFlashBag()->add(
                    'fail',
                    Translator::getInstance()->trans('This menu does not exists', [], CustomFrontMenu::DOMAIN_NAME),
                );

                if (0 === \count($menuNames)) {
                    return $this->screenData($menuNames, [], 0);
                }

                $menuId = (int) str_replace('menu-selected-', '', $menuNames[0]['id']);
                $this->rememberMenu($menuId);
                $menu = $customFrontMenuService->getMenu($menuId);
            }

            if ($menu) {
                $data = $customFrontMenuLoadService->loadTableBrowser($menu);
            }
        }

        return $this->screenData($menuNames, $data, (int) $menuId);
    }

    /**
     * @param array<int, array<string, string>> $menuNames
     * @param array<int, mixed>                 $menuItems
     *
     * @return array<string, mixed>
     */
    private function screenData(array $menuNames, array $menuItems, int $menuId): array
    {
        $locale = $this->theliaSession()->getAdminLang()->getLocale();

        return [
            'menuNames' => json_encode($menuNames),
            'menuItems' => json_encode($menuItems),
            'currentMenuId' => $menuId,
            'locale' => $locale,
            'targets' => $this->targetCatalog->targets($locale),
        ];
    }
}
