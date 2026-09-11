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

use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * The code a theme calls a menu by.
 *
 * Static because the module's update() has to fill the codes of menus created before the
 * column existed, and a module lifecycle method has no container to autowire from.
 */
final readonly class MenuCode
{
    private const FALLBACK = 'menu';

    public static function slug(string $source): string
    {
        $slug = (new AsciiSlugger())->slug($source)->lower()->toString();

        // A name written entirely in a script the slugger cannot transliterate leaves
        // nothing behind, and a menu with no code is unreachable from a theme.
        return '' === $slug ? self::FALLBACK : $slug;
    }

    /**
     * The slug itself when it is free, otherwise the first numbered variant that is.
     *
     * @throws PropelException
     */
    public static function unique(string $source, ?int $exceptId = null): string
    {
        $base = self::slug($source);
        $candidate = $base;
        $suffix = 1;

        while (self::taken($candidate, $exceptId)) {
            $candidate = $base.'-'.++$suffix;
        }

        return $candidate;
    }

    /**
     * @throws PropelException
     */
    private static function taken(string $code, ?int $exceptId): bool
    {
        $query = CustomFrontMenuItemQuery::create()->filterByCode($code);

        if (null !== $exceptId) {
            $query->filterById($exceptId, '!=');
        }

        return $query->exists();
    }
}
