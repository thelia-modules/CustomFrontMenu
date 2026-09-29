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

namespace CustomFrontMenu\Tests\Integration\Service\Front;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Service\BackOffice\MenuComposer;
use CustomFrontMenu\Service\Front\MenuTreeResolver;
use PHPUnit\Framework\Attributes\Test;
use Thelia\Model\Category;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class MenuTreeResolverTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private MenuComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new MenuComposer();
    }

    #[Test]
    public function aMenuResolvesInOrderWithItsHierarchy(): void
    {
        $category = $this->category('Shoes');
        $content = $this->createFixtureFactory()->content($this->createFixtureFactory()->folder());
        $menu = $this->composer->createMenu('Main', 'en_US', 'main');

        $shoes = $this->entry($menu, 'Our shoes', 'category', (int) $category->getId());
        $this->freeEntry($shoes, 'Sale', '/sale');
        $this->entry($menu, 'About us', 'content', (int) $content->getId());
        $this->freeEntry($menu, 'Blog', 'https://blog.example.com');

        $tree = (new MenuTreeResolver())->resolve('main', 'en_US');

        self::assertSame(['Our shoes', 'About us', 'Blog'], array_column($tree, 'title'));
        self::assertSame($category->getUrl('en_US'), $tree[0]['href']);
        self::assertSame($content->getUrl('en_US'), $tree[1]['href']);
        self::assertSame('https://blog.example.com', $tree[2]['href']);
        self::assertSame([['id' => $tree[0]['children'][0]['id'], 'title' => 'Sale', 'href' => '/sale', 'children' => []]], $tree[0]['children']);
        self::assertSame([], $tree[1]['children']);
    }

    #[Test]
    public function anEntryWhoseTargetIsHiddenIsDroppedWithItsChildren(): void
    {
        $hidden = $this->category('Hidden', visible: false);
        $menu = $this->composer->createMenu('Main', 'en_US', 'main');

        // A rendered sibling comes first: the hidden entry's child must not be taken for
        // one of its children.
        $this->freeEntry($menu, 'Kept', '/kept');
        $entry = $this->entry($menu, 'Hidden', 'category', (int) $hidden->getId());
        $this->freeEntry($entry, 'Child', '/child');
        $this->entry($menu, 'Gone', 'category', 999999999);
        $this->freeEntry($menu, 'Last', '/last');

        $tree = (new MenuTreeResolver())->resolve('main', 'en_US');

        self::assertSame(['Kept', 'Last'], array_column($tree, 'title'));
        self::assertSame([], $tree[0]['children']);
    }

    #[Test]
    public function aLabelFallsBackToEnglishThenToAnyLanguage(): void
    {
        $menu = $this->composer->createMenu('Main', 'en_US', 'main');

        $english = $this->composer->createEntry($this->fresh($menu), 'English', 'en_US');
        $this->composer->setTranslation($english, 'fr_FR', '', null);

        $this->composer->createEntry($this->fresh($menu), 'Español', 'es_ES');

        $french = $this->composer->createEntry($this->fresh($menu), 'English label', 'en_US');
        $this->composer->setTranslation($french, 'fr_FR', 'Libellé', null);

        $tree = (new MenuTreeResolver())->resolve('main', 'fr_FR');

        self::assertSame(['English', 'Español', 'Libellé'], array_column($tree, 'title'));
    }

    #[Test]
    public function aHundredEntriesDoNotCostOneQueryEach(): void
    {
        $fixtures = $this->createFixtureFactory();
        $folder = $fixtures->folder();
        $menu = $this->composer->createMenu('Main', 'en_US', 'main');

        for ($branch = 0; $branch < 10; ++$branch) {
            $parent = $this->entry($menu, 'Branch '.$branch, 'category', (int) $this->category('Category '.$branch)->getId());

            for ($leaf = 0; $leaf < 9; ++$leaf) {
                match ($leaf % 4) {
                    0 => $this->entry($parent, 'Leaf', 'content', (int) $fixtures->content($folder)->getId()),
                    1 => $this->entry($parent, 'Leaf', 'folder', (int) $fixtures->folder()->getId()),
                    2 => $this->entry($parent, 'Leaf', 'brand', (int) $fixtures->brand()->getId()),
                    default => $this->freeEntry($parent, 'Leaf', '/leaf-'.$branch.'-'.$leaf),
                };
            }
        }

        $tree = null;
        $statements = $this->recordSqlQueries(static function () use (&$tree): void {
            $tree = (new MenuTreeResolver())->resolve('main', 'en_US');
        });

        self::assertCount(10, $tree);
        self::assertSame(90, array_sum(array_map(static fn (array $node): int => \count($node['children']), $tree)));
        self::assertLessThan(20, \count($statements), 'Queries for 100 entries: '.\count($statements));
    }

    private function category(string $title, bool $visible = true): Category
    {
        $category = $this->createFixtureFactory()->category(['visible' => $visible ? 1 : 0]);
        $category->setLocale('en_US')->setTitle($title)->save();

        return $category;
    }

    private function entry(CustomFrontMenuItem $parent, string $title, string $view, int $viewId): CustomFrontMenuItem
    {
        $entry = $this->composer->createEntry($this->fresh($parent), $title, 'en_US');
        $this->composer->setTarget($entry, $view, $viewId);

        return $entry;
    }

    private function freeEntry(CustomFrontMenuItem $parent, string $title, string $url): CustomFrontMenuItem
    {
        $entry = $this->composer->createEntry($this->fresh($parent), $title, 'en_US');
        $this->composer->setTranslation($entry, 'en_US', $title, $url);

        return $entry;
    }

    /**
     * IntegrationTestCase turns Propel's instance pool off, so the nested set can no
     * longer shift the bounds of a parent held in memory: without a reload, every new
     * child would be inserted at the parent's stale right bound, in reverse order.
     */
    private function fresh(CustomFrontMenuItem $parent): CustomFrontMenuItem
    {
        $parent->reload();

        return $parent;
    }
}
