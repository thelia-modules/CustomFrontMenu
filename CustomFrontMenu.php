<?php
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

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class CustomFrontMenu extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'customfrontmenu';

    /**
     * @return bool true to continue module activation, false to prevent it
     */
    public function preActivation(ConnectionInterface $con = null)
    {
        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);

            self::setConfigValue('is_initialized', true);
        }

        return true;
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        $database = new Database($con);

        if ($deleteModuleData) {
            $database->insertSql(null, [__DIR__.'/Config/sql/destroy.sql']);
        }
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
