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
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace CustomFrontMenu;

use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Service\MenuCode;
use CustomFrontMenu\Service\MenuLink;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

class CustomFrontMenu extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'customfrontmenu';

    /**
     * Create the database when the module is activated.
     * @return bool true to continue module activation, false to prevent it
     */
    public function preActivation(?ConnectionInterface $con = null): bool
    {
        if (!self::getConfigValue('is_initialized')) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);

            self::setConfigValue('is_initialized', '1');
        }

        return true;
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $this->applyUpdateScripts($currentVersion, $newVersion, $con);
        $this->migrateToCodes($con);
    }

    /**
     * Re-run the schema migration on every activation.
     *
     * update() only fires when the recorded version differs, and the version is recorded
     * before it runs: once a shop is stamped 2.0.0 it will never be called again, however
     * it ended. Deactivating and reactivating the module is then the way out, so this has
     * to do the work too. Everything it calls is idempotent.
     */
    public function postActivation(?ConnectionInterface $con = null): void
    {
        $this->migrateToCodes($con);
    }

    private function applyUpdateScripts(string $currentVersion, string $newVersion, ?ConnectionInterface $con): void
    {
        $updateDir = __DIR__.DS.'Config'.DS.'update';

        // Finder::in() throws on a missing directory, which would abort the whole
        // module refresh: a module with no update script is a normal case.
        if (!is_dir($updateDir)) {
            return;
        }

        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in($updateDir);

        $database = new Database($con);

        foreach ($finder as $file) {
            $scriptVersion = $file->getBasename('.sql');

            // Bounded on both ends: a script for a version beyond the one being installed
            // has no business running during this update.
            if (version_compare($currentVersion, $scriptVersion, '<')
                && version_compare($scriptVersion, $newVersion, '<=')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }

    /**
     * Bring an installation created before 2.0.0 up to the code column.
     *
     * Every step asks the database what it looks like instead of trusting the module
     * version, and can be run again: ModuleManagement writes the new version before it
     * calls update() (ModuleManagement.php:137-145 then :168), and the first DDL commits
     * that write implicitly, so a failure half-way through would otherwise leave a shop
     * recorded as 2.0.0 with no way left to finish the job. Running it again from
     * postActivation() is then a real recovery path, not a formality.
     */
    private function migrateToCodes(?ConnectionInterface $con = null): void
    {
        $con ??= Propel::getWriteConnection('TheliaMain');

        $this->addCodeColumn($con);
        $this->fillMissingMenuCodes();
        $this->dropUnsafeUrls();
    }

    /**
     * `ADD COLUMN IF NOT EXISTS` and `CREATE INDEX IF NOT EXISTS` are MariaDB extensions:
     * a shop on MySQL would take a syntax error. The catalogue answers both.
     */
    private function addCodeColumn(ConnectionInterface $con): void
    {
        if (!$this->exists($con, 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :name', 'code')) {
            $con->exec('ALTER TABLE `custom_front_menu_item` ADD COLUMN `code` VARCHAR(255) NULL AFTER `id`');
        }

        if (!$this->exists($con, 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :name', 'custom_front_menu_item_code_unique')) {
            $con->exec('CREATE UNIQUE INDEX `custom_front_menu_item_code_unique` ON `custom_front_menu_item` (`code`)');
        }
    }

    private function exists(ConnectionInterface $con, string $sql, string $name): bool
    {
        $statement = $con->prepare($sql);
        $statement->execute(['table' => 'custom_front_menu_item', 'name' => $name]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Give a code to every menu created before the column existed.
     *
     * A menu with no code cannot be rendered by a theme at all, so this runs on the data
     * rather than leaving the shop owner to discover it menu by menu. Derived from the
     * menu title, which is what the person would have typed anyway.
     */
    private function fillMissingMenuCodes(): void
    {
        $menus = CustomFrontMenuItemQuery::create()
            ->filterByTreeLevel(1)
            ->filterByCode(null)
            ->find();

        foreach ($menus as $menu) {
            $title = (string) CustomFrontMenuItemI18nQuery::create()
                ->filterById($menu->getId())
                ->findOne()
                ?->getTitle();

            $menu
                ->setCode(MenuCode::derive('' === trim($title) ? 'menu-'.$menu->getId() : $title))
                ->save();
        }
    }

    /**
     * Drop the links the 1.x screen let through.
     *
     * It filtered with FILTER_SANITIZE_URL, which only strips illegal characters:
     * `javascript:` survived it intact. Those rows are rendered on every page of the shop
     * and served by a public API, so they are cleared rather than carried over. The filter
     * that decides is the one the front applies, so both can never disagree.
     */
    private function dropUnsafeUrls(): void
    {
        $translations = CustomFrontMenuItemI18nQuery::create()
            ->filterByUrl(null, Criteria::NOT_EQUAL)
            ->find();

        foreach ($translations as $translation) {
            $url = (string) $translation->getUrl();

            if ('' !== $url && null === MenuLink::filter($url)) {
                $translation->setUrl(null)->save();
            }
        }
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
