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

use CustomFrontMenu\Service\MenuCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MenuCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function codes(): iterable
    {
        yield 'letters' => ['main', true];
        yield 'letters, digits and dashes' => ['footer-2', true];
        yield 'uppercase' => ['Main', false];
        yield 'leading dash' => ['-main', false];
        yield 'trailing dash' => ['main-', false];
        yield 'double dash' => ['main--menu', false];
        yield 'space' => ['main menu', false];
        yield 'underscore' => ['main_menu', false];
        yield 'empty' => ['', false];
    }

    #[Test]
    #[DataProvider('codes')]
    public function aCodeTakesLowercaseLettersDigitsAndSingleDashes(string $code, bool $valid): void
    {
        self::assertSame($valid, MenuCode::isValid($code));
    }

    #[Test]
    public function aNameIsSluggedIntoACode(): void
    {
        self::assertSame('menu-principal-ete', MenuCode::slug('Menu principal (été)'));
    }

    #[Test]
    public function aNameThatLeavesNothingToSlugFallsBackToAGenericCode(): void
    {
        self::assertSame('menu', MenuCode::slug('!!!'));
    }
}
