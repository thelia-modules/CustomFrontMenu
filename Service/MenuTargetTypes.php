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

namespace CustomFrontMenu\Service;

use Page\Model\PageQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductQuery;

/**
 * The kinds of object a menu entry can point at, with the query that reads each one.
 *
 * Pages belong to the Page module, which a shop may not have: the kind is offered only
 * while that module is installed and active, and an entry left on a page once the module
 * is gone is treated like any other entry whose target no longer resolves.
 */
final readonly class MenuTargetTypes
{
    private const PAGE_MODULE = 'Page';

    /**
     * @return array<string, class-string> query class by kind, lowercase
     */
    public static function queries(): array
    {
        $queries = [
            'brand' => BrandQuery::class,
            'category' => CategoryQuery::class,
            'content' => ContentQuery::class,
            'folder' => FolderQuery::class,
            'product' => ProductQuery::class,
        ];

        if (self::isPageModuleActive()) {
            $queries['page'] = PageQuery::class;
        }

        return $queries;
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_keys(self::queries());
    }

    private static function isPageModuleActive(): bool
    {
        if (!class_exists(PageQuery::class)) {
            return false;
        }

        // Cached for the request by the core: every page already reads it.
        foreach (ModuleQuery::getActivated() as $module) {
            if (self::PAGE_MODULE === $module->getCode()) {
                return true;
            }
        }

        return false;
    }
}
