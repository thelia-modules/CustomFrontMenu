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

namespace CustomFrontMenu\Service\BackOffice;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Service\MenuCode;
use Propel\Runtime\Exception\PropelException;

/**
 * Every write the composition screen performs, one operation at a time.
 *
 * The 1.x screen held the whole tree in the browser and saved it by deleting and
 * recreating every row: a closed tab lost the work, and every save renumbered the ids.
 * Each action here persists on its own, the way the back-office category tree does.
 */
final readonly class MenuComposer
{
    /**
     * The single nested-set root every menu hangs from, created on first use.
     *
     * @throws PropelException
     */
    public function root(): CustomFrontMenuItem
    {
        // The generated findRoot() declares @return ChildCustomFrontMenuItem, but it is a
        // findOne() underneath: on an empty table it returns null, which is exactly the
        // case this method exists to handle.
        /** @var CustomFrontMenuItem|null $root */
        $root = CustomFrontMenuItemQuery::create()->findRoot();

        if (null === $root) {
            $root = new CustomFrontMenuItem();
            $root->makeRoot();
            $root->save();
        }

        return $root;
    }

    /**
     * @return list<CustomFrontMenuItem>
     *
     * @throws PropelException
     */
    public function menus(): array
    {
        return iterator_to_array($this->root()->getChildren());
    }

    /**
     * @throws PropelException
     */
    public function menu(int $menuId): ?CustomFrontMenuItem
    {
        $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);

        // Level 1 is a menu; anything deeper is an entry inside one.
        return $menu && 1 === $menu->getLevel() ? $menu : null;
    }

    /**
     * A menu by the code a theme calls it by. Only a level-1 row carries a code, so this
     * can never answer with the nested-set root or with an entry.
     *
     * @throws PropelException
     */
    public function menuByCode(string $code): ?CustomFrontMenuItem
    {
        $menu = CustomFrontMenuItemQuery::create()->findOneByCode($code);

        return $menu && 1 === $menu->getLevel() ? $menu : null;
    }

    /**
     * @throws PropelException
     */
    public function entry(int $itemId): ?CustomFrontMenuItem
    {
        $item = CustomFrontMenuItemQuery::create()->findOneById($itemId);

        return $item && $item->getLevel() > 1 ? $item : null;
    }

    /**
     * @throws PropelException
     */
    public function createMenu(string $title, string $locale, string $code = ''): CustomFrontMenuItem
    {
        $menu = new CustomFrontMenuItem();
        $menu->insertAsLastChildOf($this->root());
        // A typed code reached here already validated and free; only an empty one is
        // derived from the name.
        $menu->setCode('' === $code ? MenuCode::derive($title) : $code);
        $menu->save();

        $this->setTranslation($menu, $locale, $title, null);

        return $menu;
    }

    /**
     * Rename a menu, and give it the code a theme will call it by.
     *
     * An empty code is derived from the new name: a menu with no code is unreachable from
     * a theme, which is the one state this column exists to prevent.
     *
     * @throws PropelException
     */
    public function renameMenu(CustomFrontMenuItem $menu, string $title, string $code, string $locale): void
    {
        $menu
            ->setCode('' === $code ? MenuCode::derive($title, (int) $menu->getId()) : $code)
            ->save();

        $this->setTranslation($menu, $locale, $title, null);
    }

    /**
     * @throws PropelException
     */
    public function createEntry(CustomFrontMenuItem $parent, string $title, string $locale): CustomFrontMenuItem
    {
        $entry = new CustomFrontMenuItem();
        $entry->insertAsLastChildOf($parent);
        $entry->save();

        $this->setTranslation($entry, $locale, $title, null);

        return $entry;
    }

    /**
     * @throws PropelException
     */
    public function delete(CustomFrontMenuItem $item): void
    {
        foreach ($item->getDescendants() as $descendant) {
            $this->deleteTranslations((int) $descendant->getId());
        }

        $item->deleteDescendants();
        $this->deleteTranslations((int) $item->getId());
        $item->delete();
    }

    /**
     * Reparent one entry. Passing the menu itself as the new parent moves the entry back
     * to the first level of that menu.
     *
     * @throws PropelException
     */
    public function move(CustomFrontMenuItem $item, CustomFrontMenuItem $newParent): void
    {
        // Moving a node inside its own subtree would corrupt the nested set.
        if ($this->isSelfOrDescendant($item, $newParent)) {
            return;
        }

        // The entry form posts the parent on every save: keep the entry's place when it is unchanged.
        if ((int) $item->getParent()?->getId() === (int) $newParent->getId()) {
            return;
        }

        $item->moveToLastChildOf($newParent);
    }

    /**
     * @throws PropelException
     */
    public function moveUp(CustomFrontMenuItem $item): void
    {
        $sibling = $item->getPrevSibling();

        if ($sibling instanceof CustomFrontMenuItem) {
            $item->moveToPrevSiblingOf($sibling);
        }
    }

    /**
     * @throws PropelException
     */
    public function moveDown(CustomFrontMenuItem $item): void
    {
        $sibling = $item->getNextSibling();

        if ($sibling instanceof CustomFrontMenuItem) {
            $item->moveToNextSiblingOf($sibling);
        }
    }

    /**
     * @throws PropelException
     */
    public function setTarget(CustomFrontMenuItem $item, ?string $view, ?int $viewId): void
    {
        $item
            ->setView($view)
            ->setViewId($viewId)
            ->save();
    }

    /**
     * @throws PropelException
     */
    public function setNewTab(CustomFrontMenuItem $item, bool $newTab): void
    {
        $item->setNewTab($newTab)->save();
    }

    /**
     * @throws PropelException
     */
    public function setTranslation(CustomFrontMenuItem $item, string $locale, ?string $title, ?string $url): void
    {
        $translation = CustomFrontMenuItemI18nQuery::create()
            ->filterById($item->getId())
            ->findOneByLocale($locale);

        if (null === $translation) {
            $translation = new CustomFrontMenuItemI18n();
            $translation
                ->setId($item->getId())
                ->setLocale($locale);
        }

        $translation
            ->setTitle($title)
            ->setUrl($url)
            ->save();
    }

    /**
     * @return array<string, array{title: ?string, url: ?string}>
     *
     * @throws PropelException
     */
    public function translations(CustomFrontMenuItem $item): array
    {
        $translations = [];

        foreach (CustomFrontMenuItemI18nQuery::create()->findById($item->getId()) as $translation) {
            $translations[$translation->getLocale()] = [
                'title' => $translation->getTitle(),
                'url' => $translation->getUrl(),
            ];
        }

        return $translations;
    }

    /**
     * @throws PropelException
     */
    private function isSelfOrDescendant(CustomFrontMenuItem $item, CustomFrontMenuItem $candidate): bool
    {
        return $candidate->getTreeLeft() >= $item->getTreeLeft()
            && $candidate->getTreeRight() <= $item->getTreeRight();
    }

    /**
     * @throws PropelException
     */
    private function deleteTranslations(int $itemId): void
    {
        CustomFrontMenuItemI18nQuery::create()->filterById($itemId)->delete();
    }
}
