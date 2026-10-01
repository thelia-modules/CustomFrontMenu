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
namespace CustomFrontMenu\Tests\Integration\Service\BackOffice;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\Test;
use Thelia\Test\IntegrationTestCase;

final class MenuComposerTest extends IntegrationTestCase
{
    use ComposesMenus;

    #[Test]
    public function menusHangFromASingleRootInCreationOrder(): void
    {
        $main = $this->menu('main', 'Main');
        $footer = $this->menu('footer', 'Footer');

        $root = $this->composer()->root();

        self::assertSame((int) $root->getId(), (int) $this->composer()->root()->getId(), 'The root is created once.');
        self::assertSame(
            [(int) $main->getId(), (int) $footer->getId()],
            array_map(static fn (CustomFrontMenuItem $menu): int => (int) $menu->getId(), $this->composer()->menus()),
        );
        self::assertSame('Main', $this->composer()->translations($main)['en_US']['title']);
    }

    #[Test]
    public function aMenuWithoutATypedCodeGetsOneDerivedFromItsName(): void
    {
        $first = $this->composer()->createMenu('Main menu', 'en_US');
        $second = $this->composer()->createMenu('Main menu', 'en_US');

        self::assertSame('main-menu', $first->getCode());
        self::assertSame('main-menu-2', $second->getCode());
    }

    #[Test]
    public function aCodeDerivedFromAVeryLongNameFitsTheColumnEvenWhenNumbered(): void
    {
        $name = str_repeat('a', 255);
        $this->composer()->createMenu($name, 'en_US');
        $second = $this->composer()->createMenu($name, 'en_US');

        self::assertSame(str_repeat('a', 253).'-2', $second->getCode());
    }

    #[Test]
    public function renamingAMenuKeepsItsOwnCodeFree(): void
    {
        $menu = $this->menu('main-menu', 'Main menu');

        $this->composer()->renameMenu($this->fresh($menu), 'Main menu', '', 'en_US');
        self::assertSame('main-menu', $this->fresh($menu)->getCode(), 'A menu does not collide with itself.');

        $this->composer()->renameMenu($this->fresh($menu), 'Header', 'header', 'en_US');
        self::assertSame('header', $this->fresh($menu)->getCode());
        self::assertSame('Header', $this->composer()->translations($menu)['en_US']['title']);
    }

    #[Test]
    public function menusAndEntriesAreToldApart(): void
    {
        $menu = $this->menu('main');
        $entry = $this->labelEntry($menu, 'Entry');

        self::assertNotNull($this->composer()->menu((int) $menu->getId()));
        self::assertNull($this->composer()->menu((int) $entry->getId()));
        self::assertNotNull($this->composer()->entry((int) $entry->getId()));
        self::assertNull($this->composer()->entry((int) $menu->getId()));
        self::assertNull($this->composer()->entry((int) $this->composer()->root()->getId()));
        self::assertSame((int) $menu->getId(), (int) $this->composer()->menuByCode('main')?->getId());
        self::assertNull($this->composer()->menuByCode('unknown'));
    }

    #[Test]
    public function aTranslationIsWrittenOncePerLanguage(): void
    {
        $entry = $this->labelEntry($this->menu('main'), 'Sale');

        $this->composer()->setTranslation($entry, 'fr_FR', 'Promo', '/promo');
        $this->composer()->setTranslation($entry, 'fr_FR', 'Soldes', '/soldes');

        self::assertSame(
            ['en_US' => ['title' => 'Sale', 'url' => null], 'fr_FR' => ['title' => 'Soldes', 'url' => '/soldes']],
            $this->composer()->translations($entry),
        );
    }

    #[Test]
    public function aTargetIsSetAndCleared(): void
    {
        $entry = $this->labelEntry($this->menu('main'), 'Shoes');

        $this->composer()->setTarget($entry, 'Category', 12);
        self::assertSame(['Category', 12], [$this->fresh($entry)->getView(), $this->fresh($entry)->getViewId()]);

        $this->composer()->setTarget($this->fresh($entry), null, null);
        self::assertSame([null, null], [$this->fresh($entry)->getView(), $this->fresh($entry)->getViewId()]);
    }

    #[Test]
    public function anEntryMovesUnderAnotherAndBackToTheFirstLevel(): void
    {
        $menu = $this->menu('main');
        $first = $this->labelEntry($menu, 'First');
        $second = $this->labelEntry($menu, 'Second');

        $this->composer()->move($this->fresh($second), $this->fresh($first));
        self::assertSame(['First' => ['Second' => []]], $this->shape($menu));

        $this->composer()->move($this->fresh($second), $this->fresh($menu));
        self::assertSame(['First' => [], 'Second' => []], $this->shape($menu));
    }

    #[Test]
    public function anEntryIsNeverMovedIntoItsOwnSubtree(): void
    {
        $menu = $this->menu('main');
        $parent = $this->labelEntry($menu, 'Parent');
        $child = $this->labelEntry($parent, 'Child');

        $this->composer()->move($this->fresh($parent), $this->fresh($child));
        $this->composer()->move($this->fresh($parent), $this->fresh($parent));

        self::assertSame(['Parent' => ['Child' => []]], $this->shape($menu));
    }

    #[Test]
    public function anEntryMovesUpAndDownAmongItsSiblings(): void
    {
        $menu = $this->menu('main');
        $first = $this->labelEntry($menu, 'First');
        $this->labelEntry($menu, 'Second');
        $third = $this->labelEntry($menu, 'Third');

        $this->composer()->moveUp($this->fresh($third));
        $afterUp = $this->order($menu);

        $this->composer()->moveDown($this->fresh($first));
        $afterDown = $this->order($menu);

        // At either end, nothing moves.
        $this->composer()->moveUp($this->fresh($third));
        $this->composer()->moveDown($this->fresh($this->composer()->menuByCode('main')->getLastChild()));
        $atTheEnds = $this->order($menu);

        self::assertSame(['First', 'Third', 'Second'], $afterUp);
        self::assertSame(['Third', 'First', 'Second'], $afterDown);
        self::assertSame($afterDown, $atTheEnds);
    }

    #[Test]
    public function deletingAnEntryTakesItsSubtreeAndTheirTranslations(): void
    {
        $menu = $this->menu('main');
        $parent = $this->labelEntry($menu, 'Parent');
        $child = $this->labelEntry($parent, 'Child');
        $grandchild = $this->labelEntry($child, 'Grandchild');
        $sibling = $this->labelEntry($menu, 'Sibling');

        $this->composer()->delete($this->fresh($parent));

        $gone = [(int) $parent->getId(), (int) $child->getId(), (int) $grandchild->getId()];
        self::assertSame(['Sibling' => []], $this->shape($menu));
        self::assertSame(0, CustomFrontMenuItemQuery::create()->filterById($gone)->count());
        self::assertSame(0, CustomFrontMenuItemI18nQuery::create()->filterById($gone)->count());
        self::assertSame(1, CustomFrontMenuItemI18nQuery::create()->filterById($sibling->getId())->count());
    }

    #[Test]
    public function deletingAMenuTakesAllItsEntries(): void
    {
        $menu = $this->menu('main');
        $entry = $this->labelEntry($menu, 'Entry');
        $this->labelEntry($entry, 'Child');
        $other = $this->menu('footer');

        $this->composer()->delete($this->fresh($menu));

        self::assertNull($this->composer()->menuByCode('main'));
        self::assertSame(['footer'], array_map(static fn (CustomFrontMenuItem $menu): ?string => $menu->getCode(), $this->composer()->menus()));
        self::assertSame(1, CustomFrontMenuItemQuery::create()->descendantsOf($this->composer()->root())->count(), 'Only the other menu is left.');
        self::assertSame('footer', $this->fresh($other)->getCode());
    }

    /**
     * Titles of a menu's first-level entries, in order, read again from the database.
     *
     * @return list<string>
     */
    private function order(CustomFrontMenuItem $menu): array
    {
        return array_keys($this->shape($menu));
    }

    /**
     * Titles of a menu's entries, nested like the tree, read again from the database.
     *
     * @return array<string, mixed>
     */
    private function shape(CustomFrontMenuItem $parent): array
    {
        $shape = [];

        foreach ($this->fresh($parent)->getChildren() as $child) {
            $shape[(string) $this->composer()->translations($child)['en_US']['title']] = $this->shape($child);
        }

        return $shape;
    }
}
