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

/**
 * The only shape a menu link may take.
 *
 * Applied on the way in and on the way out. Validating only on the way in protects the
 * rows written by this version and nothing else: the 1.x screen filtered with
 * FILTER_SANITIZE_URL, which strips illegal characters and leaves `javascript:` intact,
 * so a shop upgrading from 1.x can carry a poisoned link into a menu rendered on every
 * page and served by a public API.
 */
final readonly class MenuLink
{
    /**
     * http(s) or site-relative, or nothing.
     */
    public static function filter(string $url): ?string
    {
        $url = trim(strip_tags($url));

        // Browsers drop tabs and line breaks from a URL before parsing it, so `/<tab>/evil.com`
        // reaches them as `//evil.com`. No legitimate link carries a control character.
        if ('' === $url || 1 === preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            // `//evil.com` is protocol-relative and `/\evil.com` is normalised to it by
            // browsers: both leave the shop while looking like an internal path.
            return 1 === preg_match('#\A/(?![/\\\\])#', $url) ? $url : null;
        }

        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
