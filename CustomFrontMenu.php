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
use Propel\Runtime\Connection\ConnectionInterface;
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
    public function preActivation(ConnectionInterface $con = null): bool
    {
        if (!self::getConfigValue('is_initialized')) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);

            self::setConfigValue('is_initialized', '1');
        }

        return true;
    }

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
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
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }

        if (version_compare($currentVersion, '2.0.0', '<')) {
            $this->fillMissingMenuCodes();
        }
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
                ->setCode(MenuCode::unique('' === trim($title) ? 'menu-'.$menu->getId() : $title))
                ->save();
        }
    }

    /**
     * Delete the cookie, but preserve the database
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        setcookie('menuId', '', time() - 3600, '/admin/module/CustomFrontMenu');
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
