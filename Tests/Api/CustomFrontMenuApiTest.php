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
namespace CustomFrontMenu\Tests\Api;

use CustomFrontMenu\Service\Front\MenuTreeResolver;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Thelia\Test\ApiTestCase;

final class CustomFrontMenuApiTest extends ApiTestCase
{
    use ComposesMenus;

    #[Test]
    public function aMenuIsReadByItsCodeWithoutBeingLoggedIn(): void
    {
        $fixtures = $this->createFixtureFactory();
        $category = $this->titledCategory($fixtures, 'Shoes');
        $shoes = $this->entry($this->menu('main'), 'Our shoes', 'Category', (int) $category->getId());
        $sale = $this->freeEntry($shoes, 'Sale', '/sale');

        $response = $this->jsonRequest('GET', '/api/front/custom-front-menus/main', format: 'json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(
            [
                'code' => 'main',
                'items' => [[
                    'id' => (int) $shoes->getId(),
                    'title' => 'Our shoes',
                    'href' => $category->getUrl('en_US'),
                    'newTab' => false,
                    'children' => [['id' => (int) $sale->getId(), 'title' => 'Sale', 'href' => '/sale', 'newTab' => false, 'children' => []]],
                ]],
            ],
            json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function aHeadlessClientPicksTheLanguageOfTheMenu(): void
    {
        $entry = $this->freeEntry($this->menu('main'), 'Sale', '/sale');
        $this->composer()->setTranslation($entry, 'fr_FR', 'Soldes', '/soldes');

        $read = function (string $query): array {
            $response = $this->jsonRequest('GET', '/api/front/custom-front-menus/main'.$query, format: 'json');

            return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['items'][0];
        };

        // Same parameter as the core front API: a stateless client has no session to carry it.
        self::assertSame(['Soldes', '/soldes'], [$read('?locale=fr_FR')['title'], $read('?locale=fr_FR')['href']]);
        // Not an active language: the shop's language, not an error.
        self::assertSame('Sale', $read('?locale=xx_XX')['title']);
        self::assertSame('Sale', $read('')['title']);
    }

    #[Test]
    public function anUnpublishedTargetIsNeverServedEvenToAnAdministrator(): void
    {
        $hidden = $this->titledCategory($this->createFixtureFactory(), 'Hidden', visible: false);
        $menu = $this->menu('main');
        $this->entry($menu, 'Hidden', 'Category', (int) $hidden->getId());
        $this->freeEntry($menu, 'Kept', '/kept');
        $token = $this->authenticateAsAdmin();

        foreach ([null, $token] as $bearer) {
            $response = $this->jsonRequest('GET', '/api/front/custom-front-menus/main', token: $bearer, format: 'json');
            $items = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['items'];

            self::assertSame(['Kept'], array_column($items, 'title'), null === $bearer ? 'anonymous' : 'administrator');
        }
    }

    #[Test]
    public function anUnknownMenuIsNotFound(): void
    {
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/custom-front-menus/unknown')->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writeMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[Test]
    #[DataProvider('writeMethods')]
    public function theFrontApiIsReadOnly(string $method): void
    {
        $this->freeEntry($this->menu('main'), 'Sale', '/sale');

        $response = $this->jsonRequest($method, '/api/front/custom-front-menus/main', ['items' => []], $this->authenticateAsAdmin());

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['Sale'], array_column((new MenuTreeResolver())->resolve('main', 'en_US'), 'title'));
    }
}
