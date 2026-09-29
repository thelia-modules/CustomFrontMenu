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

namespace CustomFrontMenu\Tests\Support;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Model\Map\CustomFrontMenuItemTableMap;
use CustomFrontMenu\Service\BackOffice\MenuComposer;
use Thelia\Model\Category;
use Thelia\Test\FixtureFactory;

/**
 * Builds menus the way the composition screen does, through MenuComposer.
 *
 * A node held in memory keeps the nested-set bounds it was read with, while every write
 * to the tree shifts them in the database: every helper rereads the node it writes under,
 * or a second child would be inserted at the parent's stale right bound. The instance
 * pool is emptied first, because a web test serves the reread from it.
 */
trait ComposesMenus
{
    private function composer(): MenuComposer
    {
        return new MenuComposer();
    }

    private function menu(string $code, string $title = 'Menu'): CustomFrontMenuItem
    {
        return $this->composer()->createMenu($title, 'en_US', $code);
    }

    private function entry(CustomFrontMenuItem $parent, string $title, string $view, int $viewId): CustomFrontMenuItem
    {
        $entry = $this->composer()->createEntry($this->fresh($parent), $title, 'en_US');
        $this->composer()->setTarget($entry, $view, $viewId);

        return $entry;
    }

    private function freeEntry(CustomFrontMenuItem $parent, string $title, string $url): CustomFrontMenuItem
    {
        $entry = $this->composer()->createEntry($this->fresh($parent), $title, 'en_US');
        $this->composer()->setTranslation($entry, 'en_US', $title, $url);

        return $entry;
    }

    private function labelEntry(CustomFrontMenuItem $parent, string $title, string $locale = 'en_US'): CustomFrontMenuItem
    {
        return $this->composer()->createEntry($this->fresh($parent), $title, $locale);
    }

    private function fresh(CustomFrontMenuItem $item): CustomFrontMenuItem
    {
        CustomFrontMenuItemTableMap::clearInstancePool();

        return CustomFrontMenuItemQuery::create()->findPk($item->getId())
            ?? throw new \LogicException('Menu item #'.$item->getId().' is gone.');
    }

    private function titledCategory(FixtureFactory $fixtures, string $title, bool $visible = true): Category
    {
        $category = $fixtures->category(['visible' => $visible ? 1 : 0]);
        $category->setLocale('en_US')->setTitle($title)->save();

        return $category;
    }
}
