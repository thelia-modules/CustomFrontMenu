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
namespace CustomFrontMenu\Tests\Http\BackOffice;

use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Thelia\Model\AdminLogQuery;
use CustomFrontMenu\Tests\Support\AdminSessionInjector;
use CustomFrontMenu\Tests\Support\ComposesMenus;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

final class MenuCompositionTest extends WebIntegrationTestCase
{
    use ComposesMenus;

    private const BASE = '/admin/module/CustomFrontMenu';

    private AdminSessionInjector $injector;

    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->dispatcher()->addSubscriber($this->injector);
        // Built directly: createFixtureFactory() pushes a synthetic request the security
        // context would then read the session from.
        $this->fixtures = new FixtureFactory($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
            $this->dispatcher()->removeSubscriber($this->injector);
        }

        parent::tearDown();
    }

    #[Test]
    public function theScreensAreClosedToAVisitor(): void
    {
        $menu = $this->menu('main');

        foreach ([self::BASE, self::BASE.'/menus/'.$menu->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertFalse($this->client->getResponse()->isSuccessful(), $url.' answers '.$this->client->getResponse()->getStatusCode());
        }

        $this->client->request('POST', self::BASE.'/menus', ['title' => 'Intruder']);
        self::assertNull($this->composer()->menuByCode('intruder'));
    }

    #[Test]
    public function anAdministratorCreatesAMenuFromTheConfigurationPage(): void
    {
        $this->loginAsAdministrator();

        $crawler = $this->client->request('GET', self::BASE);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-testid="custom-front-menu-list"]'));

        $this->submit($crawler->filter('form[action$="/CustomFrontMenu/menus"]'), ['title' => 'Main menu', 'code' => 'main']);

        $menu = $this->composer()->menuByCode('main');
        self::assertNotNull($menu);
        self::assertStringEndsWith(self::BASE.'/menus/'.$menu->getId(), (string) $this->client->getResponse()->headers->get('Location'));

        $crawler = $this->client->request('GET', self::BASE);
        self::assertCount(1, $crawler->filter('[data-testid="custom-front-menu-row-'.$menu->getId().'"]'));
    }

    #[Test]
    public function aCodeIsKeptAsTypedOrRefused(): void
    {
        $this->loginAsAdministrator();
        $this->menu('main');
        $form = $this->client->request('GET', self::BASE)->filter('form[action$="/CustomFrontMenu/menus"]');

        foreach (['Not A Code', 'main', str_repeat('a', 256)] as $code) {
            $this->submit($form, ['title' => 'Refused', 'code' => $code]);

            // Sent back to the form with a message, not left to the unique index.
            self::assertSame(302, $this->client->getResponse()->getStatusCode(), $code);
            self::assertStringEndsWith(self::BASE, (string) $this->client->getResponse()->headers->get('Location'), $code);
        }

        self::assertCount(1, $this->composer()->menus(), 'Neither menu was created.');
    }

    #[Test]
    public function anAdministratorComposesAnEntry(): void
    {
        $this->loginAsAdministrator();
        $category = $this->titledCategory($this->fixtures, 'Shoes');
        $menu = $this->menu('main');
        $parent = $this->labelEntry($menu, 'Catalogue');

        // Added under an existing entry, from the tree page.
        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->submit($tree->filter('form[action$="/menus/'.$menu->getId().'/entries"]'), ['title' => 'Our shoes', 'parent_id' => (string) $parent->getId()]);

        $entry = $this->fresh($parent)->getFirstChild();
        self::assertNotNull($entry, 'The entry was created under its parent.');

        // Targeted at a category, labelled in two languages, from the entry page.
        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), [
            'view' => 'category',
            'view_id' => (string) $category->getId(),
            'title' => ['en_US' => 'Our <b>shoes</b>', 'fr_FR' => 'Nos chaussures'],
        ]);

        $saved = $this->fresh($entry);
        self::assertSame(['Category', (int) $category->getId()], [$saved->getView(), $saved->getViewId()]);
        self::assertSame('Our shoes', $this->composer()->translations($saved)['en_US']['title'], 'Markup is stripped from a label.');
        self::assertSame('Nos chaussures', $this->composer()->translations($saved)['fr_FR']['title']);
    }

    #[Test]
    public function anEntryIsNotCreatedUnderAParentFromAnotherMenu(): void
    {
        $this->loginAsAdministrator();
        $menu = $this->menu('main');
        $elsewhere = $this->labelEntry($this->menu('footer'), 'Elsewhere');

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $this->submit($tree->filter('form[action$="/menus/'.$menu->getId().'/entries"]'), ['title' => 'Intruder', 'parent_id' => (string) $elsewhere->getId()]);

        self::assertNull($this->fresh($elsewhere)->getFirstChild(), 'No entry landed in the other menu.');
        self::assertNull($this->fresh($menu)->getFirstChild(), 'No entry was created at all.');
    }

    #[Test]
    public function savingAnEntryWithoutMovingItKeepsItsPlace(): void
    {
        $this->loginAsAdministrator();
        $menu = $this->menu('main');
        $first = $this->labelEntry($menu, 'First');
        $this->labelEntry($menu, 'Second');

        $page = $this->client->request('GET', self::BASE.'/entries/'.$first->getId());
        $this->submit($page->filter('form[action$="/entries/'.$first->getId().'"]'), [
            'parent_id' => '0',
            'view' => 'none',
            'title' => ['en_US' => 'First'],
        ]);

        self::assertSame((int) $first->getId(), (int) $this->fresh($menu)->getFirstChild()?->getId());
    }

    #[Test]
    public function theAddressFieldLetsTheBrowserSubmitASiteRelativeAddress(): void
    {
        $this->loginAsAdministrator();
        $entry = $this->freeEntry($this->menu('main'), 'Contact', '/contact-us');

        // A type="url" field makes the browser refuse "/contact-us" before the form is sent.
        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        self::assertSame('text', $page->filter('input[name="url[en_US]"]')->attr('type'));

        $field = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId().'/target-field', ['view' => 'url']);
        self::assertSame('text', $field->filter('input[name="url[en_US]"]')->attr('type'));
    }

    #[Test]
    public function aMenuNameLongerThanItsColumnIsRefused(): void
    {
        $this->loginAsAdministrator();
        $tooLong = str_repeat('é', 256);

        $this->submit($this->client->request('GET', self::BASE)->filter('form[action$="/CustomFrontMenu/menus"]'), ['title' => $tooLong]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $this->composer()->menus(), 'No menu was created.');

        $menu = $this->menu('main', 'Main');
        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $this->submit($tree->filter('form[action$="/menus/'.$menu->getId().'/rename"]'), ['title' => $tooLong, 'code' => 'main']);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('Main', $this->composer()->translations($menu)['en_US']['title']);
    }

    #[Test]
    public function anEntryNameLongerThanItsColumnIsRefused(): void
    {
        $this->loginAsAdministrator();
        $menu = $this->menu('main');

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $this->submit($tree->filter('form[action$="/menus/'.$menu->getId().'/entries"]'), ['title' => str_repeat('é', 256)]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->fresh($menu)->getFirstChild());
    }

    #[Test]
    public function anEntryLabelLongerThanItsColumnIsRefused(): void
    {
        $this->loginAsAdministrator();
        $entry = $this->labelEntry($this->menu('main'), 'Sale');

        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), ['view' => 'none', 'title' => ['en_US' => str_repeat('é', 256)]]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('Sale', $this->composer()->translations($entry)['en_US']['title']);
    }

    #[Test]
    public function anAddressLongerThanItsColumnIsRefused(): void
    {
        $this->loginAsAdministrator();
        $entry = $this->freeEntry($this->menu('main'), 'Sale', '/sale');

        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), [
            'view' => 'url',
            'title' => ['en_US' => 'Sale'],
            'url' => ['en_US' => '/'.str_repeat('a', 255)],
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('/sale', $this->composer()->translations($entry)['en_US']['url'], 'The address is not cut short either.');
    }

    #[Test]
    public function aFreeUrlLeavingTheShopIsNotSaved(): void
    {
        $this->loginAsAdministrator();
        $entry = $this->labelEntry($this->menu('main'), 'Link');

        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), [
            'view' => 'url',
            'title' => ['en_US' => 'Link'],
            'url' => ['en_US' => 'javascript:alert(1)'],
        ]);

        self::assertNull($this->composer()->translations($entry)['en_US']['url']);
    }

    #[Test]
    public function anEntryIsSetToOpenInANewTabAndBackAgain(): void
    {
        $this->loginAsAdministrator();
        $entry = $this->freeEntry($this->menu('main'), 'Blog', 'https://blog.example.com');
        $save = function (array $fields) use ($entry): void {
            $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
            $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), [
                'view' => 'url',
                'title' => ['en_US' => 'Blog'],
                'url' => ['en_US' => 'https://blog.example.com'],
                ...$fields,
            ]);
        };

        $save(['new_tab' => '1']);
        self::assertTrue($this->fresh($entry)->getNewTab());
        self::assertCount(1, $this->client->request('GET', self::BASE.'/entries/'.$entry->getId())->filter('input[name="new_tab"][checked]'));

        // An unticked box is not posted at all.
        $save([]);
        self::assertFalse($this->fresh($entry)->getNewTab());
    }

    #[Test]
    public function everyCompositionWriteLeavesATraceInTheAdminLog(): void
    {
        $this->loginAsAdministrator();
        $logged = static fn (): int => AdminLogQuery::create()->filterByMessage('CustomFrontMenu: %', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->count();
        $before = $logged();

        $this->submit($this->client->request('GET', self::BASE)->filter('form[action$="/CustomFrontMenu/menus"]'), ['title' => 'Main menu', 'code' => 'main']);
        $menu = $this->composer()->menuByCode('main');
        self::assertNotNull($menu);

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $this->submit($tree->filter('form[action$="/menus/'.$menu->getId().'/entries"]'), ['title' => 'Sale']);
        $entry = $this->fresh($menu)->getFirstChild();
        self::assertNotNull($entry);

        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), ['view' => 'url', 'title' => ['en_US' => 'Sale'], 'url' => ['en_US' => '/sale']]);

        // Created menu, created entry, saved entry.
        self::assertSame($before + 3, $logged());
        self::assertSame((int) $entry->getId(), AdminLogQuery::create()->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne()?->getResourceId());
    }

    #[Test]
    public function aFreeUrlEntryReopensAsAnAddressAndKeepsItOnSave(): void
    {
        $this->loginAsAdministrator();
        $entry = $this->freeEntry($this->menu('main'), 'Sale', '/sale');

        $page = $this->client->request('GET', self::BASE.'/entries/'.$entry->getId());
        self::assertSame('url', $page->filter('select[name="view"] option[selected]')->attr('value'));

        // Renamed only, from the form as it opened.
        $this->submit($page->filter('form[action$="/entries/'.$entry->getId().'"]'), [
            'view' => (string) $page->filter('select[name="view"] option[selected]')->attr('value'),
            'title' => ['en_US' => 'Sales'],
            'url' => ['en_US' => (string) $page->filter('input[name="url[en_US]"]')->attr('value')],
        ]);

        self::assertSame(['title' => 'Sales', 'url' => '/sale'], $this->composer()->translations($entry)['en_US']);
    }

    #[Test]
    public function aDragAndDropReparentsAnEntryWithinItsMenu(): void
    {
        $this->loginAsAdministrator();
        $menu = $this->menu('main');
        $first = $this->labelEntry($menu, 'First');
        $second = $this->labelEntry($menu, 'Second');
        $other = $this->labelEntry($this->menu('footer'), 'Elsewhere');

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $dropZone = $tree->filter('[data-bo-category-tree-move-url-value]');
        $move = static fn (int $entryId, int $newParentId): array => [
            'category_id' => (string) $entryId,
            'new_parent_id' => (string) $newParentId,
            '_token' => (string) $dropZone->attr('data-bo-category-tree-token-value'),
        ];

        // Onto another menu: refused.
        $this->client->request('POST', (string) $dropZone->attr('data-bo-category-tree-move-url-value'), $move((int) $second->getId(), (int) $other->getId()));
        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertSame(2, $this->fresh($second)->getLevel());

        // Onto a sibling: nested under it.
        $this->client->request('POST', (string) $dropZone->attr('data-bo-category-tree-move-url-value'), $move((int) $second->getId(), (int) $first->getId()));
        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertSame((int) $first->getId(), (int) $this->fresh($second)->getParent()?->getId());

        // Onto the root zone: back to the first level.
        $this->client->request('POST', (string) $dropZone->attr('data-bo-category-tree-move-url-value'), $move((int) $second->getId(), 0));
        self::assertSame((int) $menu->getId(), (int) $this->fresh($second)->getParent()?->getId());
    }

    #[Test]
    public function anEntryIsReorderedAndDeletedFromTheTree(): void
    {
        $this->loginAsAdministrator();
        $menu = $this->menu('main');
        $first = $this->labelEntry($menu, 'First');
        $second = $this->labelEntry($menu, 'Second');

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $this->submit($tree->filter('form[action$="/entries/'.$second->getId().'/up"]'), []);
        self::assertSame((int) $second->getId(), (int) $this->fresh($menu)->getFirstChild()?->getId());

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        $this->submit($tree->filter('form[action$="/entries/'.$first->getId().'/delete"]'), []);
        self::assertNull(CustomFrontMenuItemQuery::create()->findPk($first->getId()));
    }

    #[Test]
    public function anEntryWhoseTargetWasDeletedIsFlaggedOnTheTree(): void
    {
        $this->loginAsAdministrator();
        $category = $this->titledCategory($this->fixtures, 'Shoes');
        $categoryId = (int) $category->getId();
        $menu = $this->menu('main');
        $this->entry($menu, 'Our shoes', 'Category', $categoryId);
        $category->delete();

        $tree = $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('#'.$categoryId, $tree->filter('[data-testid="custom-front-menu-tree-root"]')->text());
        self::assertCount(1, $tree->filter('[data-testid="custom-front-menu-tree-root"] .border-warning'));
    }

    #[Test]
    public function aWriteWithoutTheFormTokenChangesNothing(): void
    {
        $this->loginAsAdministrator();
        $menu = $this->menu('main');
        $entry = $this->labelEntry($menu, 'Kept');

        $this->client->request('POST', self::BASE.'/menus', ['title' => 'Forged']);
        $this->client->request('POST', self::BASE.'/entries/'.$entry->getId().'/delete', ['_token' => 'forged']);
        $this->client->request('POST', self::BASE.'/menus/'.$menu->getId().'/delete');

        self::assertNull($this->composer()->menuByCode('forged'));
        self::assertNotNull(CustomFrontMenuItemQuery::create()->findPk($entry->getId()));
        self::assertNotNull($this->composer()->menuByCode('main'));
    }

    #[Test]
    public function anAdministratorWithoutTheModuleRightCannotCompose(): void
    {
        $this->injector->setAdmin($this->fixtures->restrictedAdmin([]));
        $menu = $this->menu('main');

        $this->client->request('GET', self::BASE.'/menus/'.$menu->getId());
        self::assertFalse($this->client->getResponse()->isSuccessful());
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }

    private function loginAsAdministrator(): void
    {
        $this->injector->setAdmin($this->fixtures->admin());
    }

    /**
     * Posts a rendered form with its own CSRF token and the given fields.
     *
     * @param array<string, mixed> $fields
     */
    private function submit(Crawler $form, array $fields): void
    {
        self::assertCount(1, $form, 'The form is on the page.');

        $this->client->request('POST', (string) $form->attr('action'), [
            '_token' => (string) $form->filter('input[name="_token"]')->attr('value'),
            ...$fields,
        ]);
    }
}
