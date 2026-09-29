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
namespace CustomFrontMenu\Tests\Integration\Twig;

use CustomFrontMenu\Service\Front\MenuTreeResolver;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use CustomFrontMenu\Twig\CustomFrontMenuExtension;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Test\IntegrationTestCase;

final class CustomFrontMenuExtensionTest extends IntegrationTestCase
{
    use ComposesMenus;

    #[Test]
    public function aThemeGetsTheTreeOfAMenuByItsCode(): void
    {
        $entry = $this->freeEntry($this->menu('main'), 'Sale', '/sale');
        $this->composer()->setTranslation($entry, 'fr_FR', 'Soldes', '/soldes');

        $extension = new CustomFrontMenuExtension(new MenuTreeResolver(), new RequestStack());

        self::assertSame([['id' => (int) $entry->getId(), 'title' => 'Sale', 'href' => '/sale', 'children' => []]], $extension->menu('main'));
        self::assertSame('Soldes', $extension->menu('main', 'fr_FR')[0]['title']);
    }

    #[Test]
    public function anUnknownMenuRendersNothingInsteadOfFailing(): void
    {
        $extension = new CustomFrontMenuExtension(new MenuTreeResolver(), new RequestStack());

        self::assertSame([], $extension->menu('deleted-in-the-back-office'));
    }

    #[Test]
    public function theFunctionIsRegisteredUnderTheNameThemesCall(): void
    {
        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof \Twig\Environment);

        self::assertNotNull($twig->getFunction('custom_front_menu'));
    }
}
