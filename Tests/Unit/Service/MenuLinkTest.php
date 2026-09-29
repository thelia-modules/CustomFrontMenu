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

namespace CustomFrontMenu\Tests\Unit\Service;

use CustomFrontMenu\Service\MenuLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MenuLinkTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accepted(): iterable
    {
        yield 'site-relative path' => ['/sale', '/sale'];
        yield 'path with query and fragment' => ['/search?q=shoes#top', '/search?q=shoes#top'];
        yield 'http' => ['http://example.com', 'http://example.com'];
        yield 'https, any case' => ['HTTPS://example.com/a', 'HTTPS://example.com/a'];
        yield 'surrounding spaces' => ['  /sale  ', '/sale'];
        yield 'markup stripped' => ['<b>/sale</b>', '/sale'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript, mixed case' => ['JaVaScRiPt:alert(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'mailto' => ['mailto:shop@example.com'];
        yield 'protocol-relative' => ['//evil.com'];
        yield 'backslash normalised to protocol-relative' => ['/\\evil.com'];
        yield 'relative without a leading slash' => ['sale'];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function anHttpOrSiteRelativeLinkIsKept(string $url, string $expected): void
    {
        self::assertSame($expected, MenuLink::filter($url));
    }

    #[Test]
    #[DataProvider('refused')]
    public function anyOtherLinkIsRefused(string $url): void
    {
        self::assertNull(MenuLink::filter($url));
    }
}
