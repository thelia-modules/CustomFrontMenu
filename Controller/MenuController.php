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
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Service\BackOffice\MenuComposer;
use CustomFrontMenu\Service\BackOffice\MenuTargetCatalog;
use CustomFrontMenu\Service\BackOffice\MenuTreePresenter;
use CustomFrontMenu\Service\MenuCode;
use CustomFrontMenu\Service\MenuLink;
use CustomFrontMenu\Service\MenuTargetTypes;
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

/**
 * Composition of the front menus, in the default-twig back-office.
 *
 * One action per write, each persisted on its own: the 1.x screen kept the whole tree in
 * the browser and saved it by dropping and recreating every row.
 */
#[Route('/admin/module/CustomFrontMenu', name: 'admin.customfrontmenu')]
class MenuController extends BaseAdminController
{
    private const DOMAIN = CustomFrontMenu::DOMAIN_NAME;

    public function __construct(
        private readonly MenuComposer $composer,
        private readonly MenuTreePresenter $presenter,
        private readonly MenuTargetCatalog $targetCatalog,
    ) {
    }

    // ---------------------------------------------------------------- menus

    /**
     * The list of menus, rendered into the module configuration page by ConfigHook.
     *
     * @return array<string, mixed>
     *
     * @throws PropelException
     */
    public function menuListData(): array
    {
        $locale = $this->locale();
        $menus = [];

        foreach ($this->composer->menus() as $menu) {
            $menus[] = [
                'id' => (int) $menu->getId(),
                'code' => (string) $menu->getCode(),
                'title' => $this->presenter->title($menu, $locale),
                'entryCount' => \count($menu->getDescendants()),
            ];
        }

        return ['menus' => $menus];
    }

    /**
     * @throws PropelException
     */
    #[Route('/menus', name: '.menus.create', methods: ['POST'])]
    public function createMenu(Request $request): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $title = trim((string) $request->request->get('title', ''));

        if ('' === $title) {
            return $this->failure('A menu name is required', $this->configurationUrl());
        }

        $code = trim((string) $request->request->get('code', ''));

        if (null !== $rejected = $this->rejectBadCode($code, null, $this->configurationUrl())) {
            return $rejected;
        }

        $menu = $this->composer->createMenu($title, $this->locale(), $code);

        $this->log(AccessManager::CREATE, \sprintf('Menu "%s" (code %s) created', $title, $menu->getCode()), $menu);
        $this->success('New menu added successfully');

        return new RedirectResponse($this->menuUrl((int) $menu->getId()));
    }

    /**
     * @throws PropelException
     */
    #[Route('/menus/{menuId}/delete', name: '.menus.delete', methods: ['POST'], requirements: ['menuId' => '\d+'])]
    public function deleteMenu(Request $request, int $menuId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $menu = $this->composer->menu($menuId);

        if (null === $menu) {
            return $this->failure('This menu does not exist', $this->configurationUrl());
        }

        $this->log(AccessManager::DELETE, \sprintf('Menu "%s" (code %s) deleted', $this->presenter->title($menu, $this->locale()), $menu->getCode()), $menu);
        $this->composer->delete($menu);
        $this->success('Current menu deleted successfully');

        return new RedirectResponse($this->configurationUrl());
    }

    /**
     * Rename a menu and set the code a theme calls it by.
     *
     * A code typed once at creation would otherwise be permanent, and it is the part a
     * theme depends on.
     *
     * @throws PropelException
     */
    #[Route('/menus/{menuId}/rename', name: '.menus.rename', methods: ['POST'], requirements: ['menuId' => '\d+'])]
    public function renameMenu(Request $request, int $menuId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $menu = $this->composer->menu($menuId);

        if (null === $menu) {
            return $this->failure('This menu does not exist', $this->configurationUrl());
        }

        $title = trim((string) $request->request->get('title', ''));

        if ('' === $title) {
            return $this->failure('A menu name is required', $this->menuUrl($menuId));
        }

        $code = trim((string) $request->request->get('code', ''));

        if (null !== $rejected = $this->rejectBadCode($code, $menuId, $this->menuUrl($menuId))) {
            return $rejected;
        }

        $this->composer->renameMenu($menu, $title, $code, $this->locale());
        $this->log(AccessManager::UPDATE, \sprintf('Menu "%s" (code %s) saved', $title, $menu->getCode()), $menu);

        $this->success('This menu has been successfully saved');

        return new RedirectResponse($this->menuUrl($menuId));
    }

    /**
     * The tree of one menu.
     *
     * @throws PropelException
     */
    #[Route('/menus/{menuId}', name: '.menus.show', methods: ['GET'], requirements: ['menuId' => '\d+'])]
    public function showMenu(int $menuId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed(null)) {
            return $denied;
        }

        $menu = $this->composer->menu($menuId);

        if (null === $menu) {
            return new RedirectResponse($this->configurationUrl());
        }

        $locale = $this->locale();
        $tree = $this->presenter->tree($menu, $locale);

        return $this->render('custom-front-menu/tree', [
            'menuId' => $menuId,
            'menuCode' => (string) $menu->getCode(),
            'menuTitle' => $this->presenter->title($menu, $locale),
            'tree' => $tree,
            // The "add an entry" form picks its parent from a flat list: a shared modal
            // filled from the clicked row would need module JavaScript.
            'flatTree' => $this->flatten($tree),
            'preselectedParent' => (int) $this->getRequest()->query->get('parent', 0),
        ]);
    }

    // --------------------------------------------------------------- entries

    /**
     * @throws PropelException
     */
    #[Route('/menus/{menuId}/entries', name: '.entries.create', methods: ['POST'], requirements: ['menuId' => '\d+'])]
    public function createEntry(Request $request, int $menuId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $menu = $this->composer->menu($menuId);

        if (null === $menu) {
            return $this->failure('This menu does not exist', $this->configurationUrl());
        }

        $parentId = (int) $request->request->get('parent_id', 0);
        $parent = $menu;

        if ($parentId > 0) {
            $candidate = $this->composer->entry($parentId);

            if (null === $candidate || $this->menuOf($candidate)?->getId() !== $menu->getId()) {
                return $this->failure('This menu entry does not exist', $this->menuUrl($menuId));
            }

            $parent = $candidate;
        }

        $title = trim((string) $request->request->get('title', ''));

        if ('' === $title) {
            return $this->failure('An entry name is required', $this->menuUrl($menuId));
        }

        $entry = $this->composer->createEntry($parent, $title, $this->locale());
        $this->log(AccessManager::CREATE, \sprintf('Entry "%s" created in menu %s', $title, $menu->getCode()), $entry);

        return new RedirectResponse($this->entryUrl((int) $entry->getId()));
    }

    /**
     * @throws PropelException
     */
    #[Route('/entries/{itemId}', name: '.entries.edit', methods: ['GET'], requirements: ['itemId' => '\d+'])]
    public function editEntry(int $itemId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed(null)) {
            return $denied;
        }

        $entry = $this->composer->entry($itemId);

        if (null === $entry) {
            return new RedirectResponse($this->configurationUrl());
        }

        $menu = $this->menuOf($entry);
        $menuId = (int) $menu?->getId();
        $locale = $this->locale();
        $translations = $this->composer->translations($entry);

        return $this->render('custom-front-menu/entry', [
            'itemId' => $itemId,
            'menuId' => $menuId,
            'menuTitle' => $this->presenter->title($this->composer->menu($menuId), $locale),
            'entryTitle' => $this->presenter->title($entry, $locale),
            'targetTitle' => $this->presenter->targetTitle($entry, $locale),
            'translations' => $translations,
            'view' => $this->kindOf($entry, $translations),
            'viewId' => (int) $entry->getViewId(),
            'newTab' => (bool) $entry->getNewTab(),
            'kinds' => MenuTargetTypes::kinds(),
            'targets' => $this->targetCatalog->targets($locale),
            // Reparenting from the form, because dropping an entry back on the root zone of
            // the tree means aiming at a few pixels.
            'parents' => null === $menu ? [] : $this->parentOptions($menu, $entry, $locale),
            'parentId' => 2 === $entry->getLevel() ? 0 : (int) $entry->getParent()?->getId(),
        ]);
    }

    /**
     * The target field alone, swapped in by HTMX when the kind of target changes. Keeps
     * the dependent field server-rendered instead of shipping module JavaScript.
     *
     * @throws PropelException
     */
    #[Route('/entries/{itemId}/target-field', name: '.entries.target_field', methods: ['GET'], requirements: ['itemId' => '\d+'])]
    public function entryTargetField(Request $request, int $itemId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed(null)) {
            return $denied;
        }

        $entry = $this->composer->entry($itemId);

        if (null === $entry) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $view = strtolower((string) $request->query->get('view', 'none'));
        $kinds = MenuTargetTypes::kinds();

        return $this->render('custom-front-menu/_target_field', [
            'view' => \in_array($view, [...$kinds, 'url'], true) ? $view : 'none',
            'kinds' => $kinds,
            'viewId' => (int) $entry->getViewId(),
            'translations' => $this->composer->translations($entry),
            'targets' => $this->targetCatalog->targets($this->locale()),
        ]);
    }

    /**
     * @throws PropelException
     */
    #[Route('/entries/{itemId}', name: '.entries.save', methods: ['POST'], requirements: ['itemId' => '\d+'])]
    public function saveEntry(Request $request, int $itemId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $entry = $this->composer->entry($itemId);

        if (null === $entry) {
            return $this->failure('This menu entry does not exist', $this->configurationUrl());
        }

        if ($request->request->has('parent_id')) {
            $menu = $this->menuOf($entry);
            $parentId = (int) $request->request->get('parent_id', 0);
            $newParent = $parentId > 0 ? $this->composer->entry($parentId) : $menu;

            if (null === $menu || null === $newParent || $this->menuOf($newParent)?->getId() !== $menu->getId()) {
                return $this->failure('This menu entry does not exist', $this->entryUrl($itemId));
            }

            $this->composer->move($entry, $newParent);
        }

        $view = strtolower(trim((string) $request->request->get('view', 'none')));
        $titles = (array) $request->request->all('title');
        $urls = (array) $request->request->all('url');

        if (\in_array($view, MenuTargetTypes::kinds(), true)) {
            $viewId = (int) $request->request->get('view_id', 0);

            if ($viewId <= 0) {
                return $this->failure('Pick a target for this entry', $this->entryUrl($itemId));
            }

            $this->composer->setTarget($entry, ucfirst($view), $viewId);
        } else {
            $this->composer->setTarget($entry, null, null);
        }

        foreach ($titles as $locale => $title) {
            $this->composer->setTranslation(
                $entry,
                (string) $locale,
                $this->cleanTitle((string) $title),
                'url' === $view ? MenuLink::filter((string) ($urls[$locale] ?? '')) : null,
            );
        }

        $this->composer->setNewTab($entry, $request->request->getBoolean('new_tab'));
        $this->log(AccessManager::UPDATE, \sprintf('Entry "%s" saved', $this->presenter->title($entry, $this->locale())), $entry);

        $this->success('This entry has been successfully saved');

        return new RedirectResponse($this->menuUrl((int) $this->menuOf($entry)?->getId()));
    }

    /**
     * @throws PropelException
     */
    #[Route('/entries/{itemId}/delete', name: '.entries.delete', methods: ['POST'], requirements: ['itemId' => '\d+'])]
    public function deleteEntry(Request $request, int $itemId): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $entry = $this->composer->entry($itemId);

        if (null === $entry) {
            return $this->failure('This menu entry does not exist', $this->configurationUrl());
        }

        $menuId = (int) $this->menuOf($entry)?->getId();
        $this->log(AccessManager::DELETE, \sprintf('Entry "%s" deleted, with its own entries', $this->presenter->title($entry, $this->locale())), $entry);
        $this->composer->delete($entry);

        $this->success('This entry has been deleted');

        return new RedirectResponse($this->menuUrl($menuId));
    }

    // -------------------------------------------------------------- moving

    /**
     * Reparent an entry by drag and drop.
     *
     * The field names are those the back-office bo-category-tree Stimulus controller
     * posts: reusing the theme's tree controller means accepting its contract, which is
     * still cheaper than shipping a second drag and drop implementation.
     *
     * @throws PropelException
     */
    #[Route('/entries/move', name: '.entries.move', methods: ['POST'])]
    public function moveEntry(Request $request): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $entry = $this->composer->entry((int) $request->request->get('category_id', 0));
        $newParentId = (int) $request->request->get('new_parent_id', 0);

        if (null === $entry) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $menu = $this->menuOf($entry);
        $newParent = $newParentId > 0 ? $this->composer->entry($newParentId) : $menu;

        // A drop outside this menu, or onto the entry's own subtree, is a no-op.
        if (null === $newParent || null === $menu || $this->menuOf($newParent)?->getId() !== $menu->getId()) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->composer->move($entry, $newParent);
        $this->log(AccessManager::UPDATE, \sprintf('Entry "%s" moved', $this->presenter->title($entry, $this->locale())), $entry);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws PropelException
     */
    #[Route('/entries/{itemId}/up', name: '.entries.up', methods: ['POST'], requirements: ['itemId' => '\d+'])]
    public function moveEntryUp(Request $request, int $itemId): Response
    {
        return $this->reorder($request, $itemId, up: true);
    }

    /**
     * @throws PropelException
     */
    #[Route('/entries/{itemId}/down', name: '.entries.down', methods: ['POST'], requirements: ['itemId' => '\d+'])]
    public function moveEntryDown(Request $request, int $itemId): Response
    {
        return $this->reorder($request, $itemId, up: false);
    }

    /**
     * @throws PropelException
     */
    private function reorder(Request $request, int $itemId, bool $up): Response
    {
        if (null !== $denied = $this->denyUnlessAllowed($request)) {
            return $denied;
        }

        $entry = $this->composer->entry($itemId);

        if (null === $entry) {
            return $this->failure('This menu entry does not exist', $this->configurationUrl());
        }

        $up ? $this->composer->moveUp($entry) : $this->composer->moveDown($entry);
        $this->log(AccessManager::UPDATE, \sprintf('Entry "%s" moved %s', $this->presenter->title($entry, $this->locale()), $up ? 'up' : 'down'), $entry);

        return new RedirectResponse($this->menuUrl((int) $this->menuOf($entry)?->getId()));
    }

    // ------------------------------------------------------------- plumbing

    /**
     * Composing a menu is an administration operation. A null request means a GET screen,
     * which needs the permission but carries no token.
     */
    private function denyUnlessAllowed(?Request $request): ?Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFrontMenu', AccessManager::UPDATE)) {
            return $response;
        }

        if ($request instanceof Request) {
            $this->getTokenProvider()->checkToken((string) $request->request->get('_token', ''));
        }

        return null;
    }

    /**
     * A code is what a theme is written against, so a typed one is kept exactly as typed
     * or refused — never quietly slugified into something else. An empty code is not an
     * error: it means "derive it from the name".
     *
     * @throws PropelException
     */
    private function rejectBadCode(string $code, ?int $exceptId, string $redirectTo): ?RedirectResponse
    {
        if ('' === $code) {
            return null;
        }

        if (!MenuCode::isValid($code)) {
            return $this->failure('A code takes lowercase letters, digits and single dashes only', $redirectTo);
        }

        if (MenuCode::isTaken($code, $exceptId)) {
            return $this->failure('This code is already used by another menu', $redirectTo);
        }

        return null;
    }

    /**
     * The kind the edit form opens on. A free address is stored with no kind at all, only
     * its URL, so it has to be recognised from its translations: opening it as "no target"
     * would erase the address on the next save.
     *
     * @param array<string, array{title: ?string, url: ?string}> $translations
     */
    private function kindOf(CustomFrontMenuItem $entry, array $translations): string
    {
        $view = strtolower((string) $entry->getView());

        if (\in_array($view, MenuTargetTypes::kinds(), true)) {
            return $view;
        }

        foreach ($translations as $translation) {
            if ('' !== trim((string) $translation['url'])) {
                return 'url';
            }
        }

        return 'none';
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array{id: int, title: string, depth: int}>
     */
    private function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[] = ['id' => $node['id'], 'title' => $node['title'], 'depth' => $node['depth']];
            $flat = [...$flat, ...$this->flatten($node['children'])];
        }

        return $flat;
    }

    /**
     * Where one entry may be moved: every entry of its menu, minus itself and its own
     * descendants, which the nested set cannot absorb.
     *
     * @return list<array{id: int, title: string, depth: int}>
     *
     * @throws PropelException
     */
    private function parentOptions(CustomFrontMenuItem $menu, CustomFrontMenuItem $entry, string $locale): array
    {
        $excluded = [(int) $entry->getId()];

        foreach ($entry->getDescendants() as $descendant) {
            $excluded[] = (int) $descendant->getId();
        }

        return array_values(array_filter(
            $this->flatten($this->presenter->tree($menu, $locale)),
            static fn (array $option): bool => !\in_array($option['id'], $excluded, true),
        ));
    }

    /**
     * @throws PropelException
     */
    private function menuOf(CustomFrontMenuItem $item): ?CustomFrontMenuItem
    {
        $current = $item;

        while ($current->getLevel() > 1) {
            $parent = $current->getParent();

            if (!$parent instanceof CustomFrontMenuItem) {
                return null;
            }

            $current = $parent;
        }

        return 1 === $current->getLevel() ? $current : null;
    }

    /**
     * A menu label is shown on every front page: no markup, and no back quote, which the
     * 1.x screen used as its own delimiter.
     */
    private function cleanTitle(string $title): ?string
    {
        $title = trim(strip_tags(str_replace('`', "'", $title)));

        return '' === $title ? null : $title;
    }

    /**
     * Composing a menu is an administration operation, so it leaves the same trace in the
     * admin log as any other back-office write.
     */
    private function log(string $action, string $message, CustomFrontMenuItem $item): void
    {
        $this->adminLogAppend(AdminResources::MODULE, $action, 'CustomFrontMenu: '.$message, (int) $item->getId());
    }

    private function locale(): string
    {
        return $this->theliaSession()->getAdminLang()->getLocale();
    }

    private function theliaSession(): Session
    {
        /** @var Session $session */
        $session = $this->getSession();

        return $session;
    }

    private function success(string $message): void
    {
        $this->theliaSession()->getFlashBag()->add(
            'success',
            Translator::getInstance()->trans($message, [], self::DOMAIN),
        );
    }

    private function failure(string $message, string $redirectTo): RedirectResponse
    {
        $this->theliaSession()->getFlashBag()->add(
            'error',
            Translator::getInstance()->trans($message, [], self::DOMAIN),
        );

        return new RedirectResponse($redirectTo);
    }

    private function configurationUrl(): string
    {
        return URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu');
    }

    private function menuUrl(int $menuId): string
    {
        return URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu/menus/'.$menuId);
    }

    private function entryUrl(int $itemId): string
    {
        return URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu/entries/'.$itemId);
    }
}
