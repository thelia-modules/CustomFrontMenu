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

use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Test\IntegrationTestCase;

/**
 * Saving an entry writes its place, its target, its tab and its labels, all or nothing.
 *
 * Without the test transaction: Propel nests transactions by counting them, so a rollback
 * inside an outer one undoes nothing and the test could not see it. The tables are emptied
 * by hand instead.
 */
final class SaveEntryAtomicityTest extends IntegrationTestCase
{
    use ComposesMenus;

    protected bool $useTransaction = false;

    private ConnectionInterface $con;

    protected function setUp(): void
    {
        parent::setUp();

        $this->con = Propel::getWriteConnection('TheliaMain');
        self::assertSame(0, (int) $this->con->query('SELECT COUNT(*) FROM `custom_front_menu_item`')->fetchColumn(), 'The menu tables are empty before the test, which empties them after.');
    }

    protected function tearDown(): void
    {
        $this->con->exec('DELETE FROM `custom_front_menu_item_i18n`');
        $this->con->exec('DELETE FROM `custom_front_menu_item`');

        parent::tearDown();
    }

    #[Test]
    public function aSaveThatFailsHalfWayWritesNothing(): void
    {
        $menu = $this->menu('main');
        $first = $this->labelEntry($menu, 'First');
        $second = $this->labelEntry($menu, 'Second');

        try {
            $this->composer()->saveEntry($this->fresh($second), $this->fresh($first), 'Category', 12, [
                'en_US' => ['title' => 'Renamed', 'url' => null],
                // Longer than the locale column: the last write fails.
                'xx_XXXXXX' => ['title' => 'Broken', 'url' => null],
            ], true);
            self::fail('The save went through.');
        } catch (PropelException) {
        }

        $saved = $this->fresh($second);
        self::assertSame((int) $menu->getId(), (int) $saved->getParent()?->getId(), 'Not moved.');
        self::assertSame([null, null, false], [$saved->getView(), $saved->getViewId(), (bool) $saved->getNewTab()], 'No target, no new tab.');
        self::assertSame('Second', $this->composer()->translations($saved)['en_US']['title'], 'Not renamed.');
    }
}
