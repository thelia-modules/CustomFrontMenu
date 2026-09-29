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

use CustomFrontMenu\Service\Front\MenuTreeResolver;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class MenuTreeResolverTest extends IntegrationTestCase
{
    use ComposesMenus;
    use RecordsSqlQueries;

    #[Test]
    public function aMenuResolvesInOrderWithItsHierarchy(): void
    {
        $fixtures = $this->createFixtureFactory();
        $category = $this->titledCategory($fixtures, 'Shoes');
        $content = $fixtures->content($fixtures->folder());
        $menu = $this->menu('main');

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
    public function everyTargetTypeResolvesToThePublicUrlOfItsTarget(): void
    {
        $fixtures = $this->createFixtureFactory();
        $category = $this->titledCategory($fixtures, 'Category');
        $folder = $fixtures->folder();
        $content = $fixtures->content($folder);
        $brand = $fixtures->brand();
        $product = $fixtures->product($category, $fixtures->taxRule(), $fixtures->currency());
        $menu = $this->menu('main');

        // The back-office stores the type capitalised: the resolver must not care.
        $this->entry($menu, 'Brand', 'Brand', (int) $brand->getId());
        $this->entry($menu, 'Category', 'category', (int) $category->getId());
        $this->entry($menu, 'Content', 'Content', (int) $content->getId());
        $this->entry($menu, 'Folder', 'folder', (int) $folder->getId());
        $this->entry($menu, 'Product', 'Product', (int) $product->getId());

        $tree = (new MenuTreeResolver())->resolve('main', 'en_US');

        self::assertSame(
            [
                'Brand' => $brand->getUrl('en_US'),
                'Category' => $category->getUrl('en_US'),
                'Content' => $content->getUrl('en_US'),
                'Folder' => $folder->getUrl('en_US'),
                'Product' => $product->getUrl('en_US'),
            ],
            array_column($tree, 'href', 'title'),
        );
    }

    #[Test]
    public function anEntryWhoseTargetIsHiddenIsDroppedWithItsChildren(): void
    {
        $fixtures = $this->createFixtureFactory();
        $hidden = $this->titledCategory($fixtures, 'Hidden', visible: false);
        $hiddenProduct = $fixtures->product($this->titledCategory($fixtures, 'Shelf'), $fixtures->taxRule(), $fixtures->currency(), ['visible' => 0]);
        $menu = $this->menu('main');

        // A rendered sibling comes first: the hidden entry's child must not be taken for
        // one of its children.
        $this->freeEntry($menu, 'Kept', '/kept');
        $entry = $this->entry($menu, 'Hidden', 'category', (int) $hidden->getId());
        $this->freeEntry($entry, 'Child', '/child');
        $this->entry($menu, 'Gone', 'category', 999999999);
        $this->entry($menu, 'Hidden product', 'product', (int) $hiddenProduct->getId());
        $this->freeEntry($menu, 'Last', '/last');

        $tree = (new MenuTreeResolver())->resolve('main', 'en_US');

        self::assertSame(['Kept', 'Last'], array_column($tree, 'title'));
        self::assertSame([], $tree[0]['children']);
    }

    #[Test]
    public function aLabelFallsBackToEnglishThenToAnyLanguage(): void
    {
        $menu = $this->menu('main');

        $english = $this->labelEntry($menu, 'English');
        $this->composer()->setTranslation($english, 'fr_FR', '', null);

        $this->labelEntry($menu, 'Español', 'es_ES');

        // English wins over a language read before it: rows come back in locale order.
        $germanFirst = $this->labelEntry($menu, 'Angebote', 'de_DE');
        $this->composer()->setTranslation($germanFirst, 'en_US', 'Sale', null);

        $french = $this->labelEntry($menu, 'English label');
        $this->composer()->setTranslation($french, 'fr_FR', 'Libellé', null);

        $tree = (new MenuTreeResolver())->resolve('main', 'fr_FR');

        self::assertSame(['English', 'Español', 'Sale', 'Libellé'], array_column($tree, 'title'));
    }

    #[Test]
    public function aFreeUrlIsTranslatedLikeItsLabel(): void
    {
        $menu = $this->menu('main');
        $entry = $this->freeEntry($menu, 'Sale', '/sale');
        $this->composer()->setTranslation($entry, 'fr_FR', 'Soldes', '/soldes');

        self::assertSame('/soldes', (new MenuTreeResolver())->resolve('main', 'fr_FR')[0]['href']);
        self::assertSame('/sale', (new MenuTreeResolver())->resolve('main', 'en_US')[0]['href']);
    }

    #[Test]
    public function aFreeUrlSavedBeforeTheFilterIsNotServed(): void
    {
        $menu = $this->menu('main');
        // What a 1.x row can hold: its screen filtered with FILTER_SANITIZE_URL only.
        $this->freeEntry($menu, 'Poisoned', 'javascript:alert(1)');

        self::assertSame('', (new MenuTreeResolver())->resolve('main', 'en_US')[0]['href']);
    }

    #[Test]
    public function eachMenuIsReadByItsOwnCode(): void
    {
        $this->freeEntry($this->menu('main'), 'Main entry', '/main');
        $this->freeEntry($this->menu('footer'), 'Footer entry', '/footer');
        $this->menu('empty');

        $resolver = new MenuTreeResolver();

        self::assertSame(['Main entry'], array_column($resolver->resolve('main', 'en_US'), 'title'));
        self::assertSame(['Footer entry'], array_column($resolver->resolve('footer', 'en_US'), 'title'));
        self::assertSame([], $resolver->resolve('empty', 'en_US'));
    }

    #[Test]
    public function anUnknownCodeIsNotAMenu(): void
    {
        $this->menu('main');

        self::assertNull((new MenuTreeResolver())->resolve('unknown', 'en_US'));
        self::assertNull((new MenuTreeResolver())->resolve('', 'en_US'));
    }

    #[Test]
    public function aCodeCarriedByAnEntryDoesNotMakeItAMenu(): void
    {
        $entry = $this->freeEntry($this->menu('main'), 'Entry', '/entry');
        $this->fresh($entry)->setCode('entry-code')->save();

        self::assertNull((new MenuTreeResolver())->resolve('entry-code', 'en_US'));
    }

    #[Test]
    public function aMenuResolvesWithoutARequest(): void
    {
        $fixtures = $this->createFixtureFactory();
        $category = $this->titledCategory($fixtures, 'Shoes');
        $this->entry($this->menu('main'), 'Shoes', 'category', (int) $category->getId());

        // What a console command or a queued job sees.
        $requestStack = self::getContainer()->get('request_stack');
        \assert($requestStack instanceof RequestStack);
        $popped = [];
        while (null !== $request = $requestStack->pop()) {
            $popped[] = $request;
        }

        try {
            $tree = (new MenuTreeResolver())->resolve('main', 'en_US');
        } finally {
            foreach (array_reverse($popped) as $request) {
                $requestStack->push($request);
            }
        }

        self::assertSame([['id' => $tree[0]['id'], 'title' => 'Shoes', 'href' => $category->getUrl('en_US'), 'children' => []]], $tree);
    }

    #[Test]
    public function aHundredEntriesDoNotCostOneQueryEach(): void
    {
        $fixtures = $this->createFixtureFactory();
        $folder = $fixtures->folder();
        $menu = $this->menu('main');

        for ($branch = 0; $branch < 10; ++$branch) {
            $parent = $this->entry($menu, 'Branch '.$branch, 'category', (int) $this->titledCategory($fixtures, 'Category '.$branch)->getId());

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
}
