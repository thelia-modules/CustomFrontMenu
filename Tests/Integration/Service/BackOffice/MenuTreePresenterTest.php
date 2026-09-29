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

use CustomFrontMenu\Service\BackOffice\MenuTreePresenter;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Test\IntegrationTestCase;

final class MenuTreePresenterTest extends IntegrationTestCase
{
    use ComposesMenus;

    #[Test]
    public function theScreenSaysWhatEachEntryPointsAt(): void
    {
        $fixtures = $this->createFixtureFactory();
        $category = $this->titledCategory($fixtures, 'Shoes');
        $menu = $this->menu('main');

        $shoes = $this->entry($menu, 'Our shoes', 'Category', (int) $category->getId());
        $this->freeEntry($shoes, 'Sale', '/sale');
        $this->labelEntry($menu, 'Heading');

        $tree = $this->presenter()->tree($this->fresh($menu), 'en_US');

        self::assertSame('Our shoes', $tree[0]['title']);
        self::assertSame(0, $tree[0]['depth']);
        self::assertSame(['kind' => 'category', 'label' => 'Shoes', 'ok' => true], $tree[0]['target']);
        self::assertSame(1, $tree[0]['children'][0]['depth']);
        self::assertSame(['kind' => 'url', 'label' => '/sale', 'ok' => true], $tree[0]['children'][0]['target']);
        self::assertSame('none', $tree[1]['target']['kind']);
        self::assertTrue($tree[1]['target']['ok']);
    }

    #[Test]
    public function anEntryWhoseTargetWasDeletedIsReportedAsBroken(): void
    {
        $category = $this->titledCategory($this->createFixtureFactory(), 'Shoes');
        $categoryId = (int) $category->getId();
        $menu = $this->menu('main');
        $this->entry($menu, 'Our shoes', 'Category', $categoryId);

        $category->delete();

        $target = $this->presenter()->tree($this->fresh($menu), 'en_US')[0]['target'];

        self::assertSame('category', $target['kind']);
        self::assertFalse($target['ok']);
        self::assertStringContainsString('#'.$categoryId, $target['label']);
    }

    #[Test]
    public function anEntryWhoseTargetIsOfflineIsReportedAsBroken(): void
    {
        $category = $this->titledCategory($this->createFixtureFactory(), 'Shoes', visible: false);
        $menu = $this->menu('main');
        $this->entry($menu, 'Our shoes', 'Category', (int) $category->getId());

        $target = $this->presenter()->tree($this->fresh($menu), 'en_US')[0]['target'];

        self::assertFalse($target['ok']);
        self::assertStringContainsString('Shoes', $target['label']);
    }

    #[Test]
    public function aTitleFallsBackToAnyLanguageThenToAPlaceholder(): void
    {
        $menu = $this->menu('main');
        $spanish = $this->labelEntry($menu, 'Rebajas', 'es_ES');
        $untitled = $this->labelEntry($menu, 'temp');
        $this->composer()->setTranslation($untitled, 'en_US', null, null);

        self::assertSame('Rebajas', $this->presenter()->title($spanish, 'fr_FR'));
        self::assertNotSame('', $this->presenter()->title($untitled, 'en_US'));
    }

    private function presenter(): MenuTreePresenter
    {
        $translator = self::getContainer()->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return new MenuTreePresenter($translator);
    }
}
