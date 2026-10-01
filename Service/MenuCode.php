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
 * A code typed by hand is validated and kept as it was typed, never silently rewritten:
 * someone who types "Header!" and is handed "header" has to discover the difference by
 * reading the call the screen shows them. Only a code derived from the menu name is
 * slugified, because there the person did not choose the characters.
 *
 * Static because the module's update() has to fill the codes of menus created before the
 * column existed, and a module lifecycle method has no container to autowire from.
 */
final readonly class MenuCode
{
    /**
     * Lowercase letters, digits, and single dashes between them. What survives being put
     * in a Twig call, a URL path and a theme's source without quoting.
     */
    private const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const FALLBACK = 'menu';

    /** The size of the code column. */
    public const MAX_LENGTH = 255;

    public static function isValid(string $code): bool
    {
        return \strlen($code) <= self::MAX_LENGTH && 1 === preg_match(self::PATTERN, $code);
    }

    /**
     * @throws PropelException
     */
    public static function isTaken(string $code, ?int $exceptId = null): bool
    {
        $query = CustomFrontMenuItemQuery::create()->filterByCode($code);

        if (null !== $exceptId) {
            $query->filterById($exceptId, '!=');
        }

        return $query->exists();
    }

    public static function slug(string $source): string
    {
        $slug = self::fit((new AsciiSlugger())->slug($source)->lower()->toString(), self::MAX_LENGTH);

        // A name written entirely in a script the slugger cannot transliterate leaves
        // nothing behind, and a menu with no code is unreachable from a theme.
        return self::isValid($slug) ? $slug : self::FALLBACK;
    }

    /**
     * A code derived from a menu name: the slug itself when it is free, otherwise the
     * first numbered variant that is.
     *
     * Numbering is for derived codes only. A typed code that collides is refused, so the
     * person is told rather than handed a code they did not ask for.
     *
     * @throws PropelException
     */
    public static function derive(string $source, ?int $exceptId = null): string
    {
        $base = self::slug($source);
        $candidate = $base;
        $suffix = 1;

        while (self::isTaken($candidate, $exceptId)) {
            $number = '-'.++$suffix;
            $candidate = self::fit($base, self::MAX_LENGTH - \strlen($number)).$number;
        }

        return $candidate;
    }

    /**
     * Cut a slug to a length without leaving a dash at its end, which the pattern refuses.
     */
    private static function fit(string $slug, int $length): string
    {
        return rtrim(substr($slug, 0, $length), '-');
    }
}
