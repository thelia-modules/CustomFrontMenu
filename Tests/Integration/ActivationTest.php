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
namespace CustomFrontMenu\Tests\Integration;

use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Install\Database;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Activating the module creates its tables on a fresh install, and never touches the menus
 * of a shop that already has them.
 *
 * The install script starts with DROP TABLE, which commits implicitly: no test transaction,
 * the tables are restored and emptied by hand.
 */
final class ActivationTest extends IntegrationTestCase
{
    use ComposesMenus;

    protected bool $useTransaction = false;

    private ConnectionInterface $con;

    protected function setUp(): void
    {
        parent::setUp();

        $this->con = Propel::getWriteConnection('TheliaMain');

        if ($this->hasTable()) {
            self::assertSame(0, (int) $this->con->query('SELECT COUNT(*) FROM `custom_front_menu_item`')->fetchColumn(), 'The menu tables are empty before the test, which empties them after.');
        }
    }

    protected function tearDown(): void
    {
        if (!$this->hasTable()) {
            (new Database($this->con))->insertSql(null, [\dirname(__DIR__, 2).'/Config/TheliaMain.sql']);
        }

        $this->con->exec('DELETE FROM `custom_front_menu_item_i18n`');
        $this->con->exec('DELETE FROM `custom_front_menu_item`');
        $this->forgetInstallFlag();

        parent::tearDown();
    }

    #[Test]
    public function reactivatingTheModuleKeepsTheMenusEvenWithoutItsInstallFlag(): void
    {
        $menu = $this->menu('main');
        $this->labelEntry($menu, 'Kept');

        // A shop whose tables were created without going through preActivation(): by a
        // schema command, or with its module configuration lost.
        $this->forgetInstallFlag();

        $module = new CustomFrontMenu();
        self::assertTrue($module->preActivation($this->con));
        $module->postActivation($this->con);

        self::assertNotNull($this->composer()->menuByCode('main'));
        self::assertSame('Kept', $this->composer()->translations($this->fresh($menu)->getFirstChild())['en_US']['title']);
    }

    #[Test]
    public function activatingTheModuleOnAFreshInstallCreatesItsTables(): void
    {
        $this->con->exec('DROP TABLE `custom_front_menu_item_i18n`');
        $this->con->exec('DROP TABLE `custom_front_menu_item`');

        self::assertTrue((new CustomFrontMenu())->preActivation($this->con));

        self::assertTrue($this->hasTable());
        self::assertNotNull($this->menu('main'));
    }

    private function forgetInstallFlag(): void
    {
        $moduleId = (int) ModuleQuery::create()->findOneByCode('CustomFrontMenu')?->getId();
        ModuleConfigQuery::create()->filterByModuleId($moduleId)->filterByName('is_initialized')->delete();
    }

    private function hasTable(): bool
    {
        $statement = $this->con->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $statement->execute(['table' => 'custom_front_menu_item']);

        return (int) $statement->fetchColumn() > 0;
    }
}
