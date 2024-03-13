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
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class CustomFrontMenu extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'customfrontmenu';

    public function postActivation(ConnectionInterface $con = null)
    {
        if (! $this->getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));

            $this->setConfigValue('is_initialized', true);
        }
    }
}
