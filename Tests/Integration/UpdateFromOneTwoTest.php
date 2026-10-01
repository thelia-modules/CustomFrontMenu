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
namespace CustomFrontMenu\Tests\Integration;

use CustomFrontMenu\CustomFrontMenu;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Install\Database;
use Thelia\Test\IntegrationTestCase;

/**
 * The update of a 1.2.0 installation: the tables as 1.2.0 created them, rows as its screen
 * wrote them (one root, menus on the first level, entries below; a typed entry's kind
 * capitalised, an entry with no target typed "Empty"), then update().
 *
 * The update runs DDL, which commits implicitly: no transaction to roll back, so the test
 * restores the schema and empties the tables itself.
 */
final class UpdateFromOneTwoTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private ConnectionInterface $con;

    private string $installedSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->con = Propel::getWriteConnection('TheliaMain');
        self::assertSame(0, $this->rowCount('custom_front_menu_item'), 'The menu tables are empty before the test, which empties them after.');
        $this->installedSchema = $this->schema();

        $this->con->exec('DROP INDEX `custom_front_menu_item_code_unique` ON `custom_front_menu_item`');
        $this->con->exec('ALTER TABLE `custom_front_menu_item` DROP COLUMN `code`, DROP COLUMN `new_tab`');

        $this->con->exec(<<<'SQL'
            INSERT INTO `custom_front_menu_item` (`id`, `view`, `view_id`, `tree_left`, `tree_right`, `tree_level`) VALUES
                (1, NULL, NULL, 1, 16, 0),
                (2, NULL, NULL, 2, 7, 1),
                (3, 'Category', 1, 3, 4, 2),
                (4, NULL, NULL, 5, 6, 2),
                (5, NULL, NULL, 8, 11, 1),
                (6, NULL, NULL, 9, 10, 2),
                (7, NULL, NULL, 12, 15, 1),
                (8, 'Empty', 0, 13, 14, 2)
            SQL);
        $this->con->exec(<<<'SQL'
            INSERT INTO `custom_front_menu_item_i18n` (`id`, `locale`, `title`, `url`) VALUES
                (2, 'en_US', 'Main menu', NULL),
                (3, 'en_US', 'Shop', NULL),
                (4, 'en_US', 'Trap', 'javascript:alert(1)'),
                (4, 'fr_FR', 'Piège', 'JavaScript:alert(1)'),
                (6, 'en_US', 'Contact', '/contact-us'),
                (6, 'fr_FR', 'Contact', 'https://example.com/contact'),
                (7, 'en_US', 'Main menu', NULL),
                (8, 'en_US', 'Just a label', NULL)
            SQL);
    }

    protected function tearDown(): void
    {
        // Restored from the install script, not by update(): a broken update would
        // otherwise leave its own schema behind for the tests that follow.
        if (!$this->hasColumn('code') || $this->schema() !== $this->installedSchema) {
            (new Database($this->con))->insertSql(null, [\dirname(__DIR__, 2).'/Config/TheliaMain.sql']);
        }

        $this->con->exec('DELETE FROM `custom_front_menu_item_i18n`');
        $this->con->exec('DELETE FROM `custom_front_menu_item`');

        parent::tearDown();
    }

    #[Test]
    public function anUpdateFromOneTwoKeepsTheMenusAndCanBeRunAgain(): void
    {
        (new CustomFrontMenu())->update('1.2.0', '2.0.0', $this->con);
        $afterFirstRun = $this->rows();

        self::assertSame($this->installedSchema, $this->schema(), 'The updated table is the one a fresh install creates.');

        // Every row kept, in its place in the tree.
        self::assertSame(
            [[1, 1, 16, 0], [2, 2, 7, 1], [3, 3, 4, 2], [4, 5, 6, 2], [5, 8, 11, 1], [6, 9, 10, 2], [7, 12, 15, 1], [8, 13, 14, 2]],
            array_map(static fn (array $row): array => [$row['id'], $row['tree_left'], $row['tree_right'], $row['tree_level']], $afterFirstRun['items']),
        );
        self::assertSame(['Category', 1], [$afterFirstRun['items'][2]['view'], $afterFirstRun['items'][2]['view_id']]);
        self::assertSame(['Empty', 0], [$afterFirstRun['items'][7]['view'], $afterFirstRun['items'][7]['view_id']]);
        self::assertSame(8, \count($afterFirstRun['translations']), 'No label is lost.');

        // A code for every menu, and only for menus: from the title, numbered when two titles
        // meet, generic when there is no title at all.
        self::assertSame(
            [1 => null, 2 => 'main-menu', 3 => null, 4 => null, 5 => 'menu-5', 6 => null, 7 => 'main-menu-2', 8 => null],
            array_column($afterFirstRun['items'], 'code', 'id'),
        );
        self::assertSame([0], array_values(array_unique(array_column($afterFirstRun['items'], 'new_tab'))), 'Every 1.x entry opened in the same tab.');

        // A dangerous link removed, whatever its case; safe links kept, internal or external.
        self::assertSame(
            ['4/en_US' => null, '4/fr_FR' => null, '6/en_US' => '/contact-us', '6/fr_FR' => 'https://example.com/contact'],
            array_filter($this->urls($afterFirstRun), static fn (string $key): bool => str_starts_with($key, '4/') || str_starts_with($key, '6/'), \ARRAY_FILTER_USE_KEY),
        );

        // Run again, by a second update or by reactivating the module: nothing moves.
        (new CustomFrontMenu())->update('1.2.0', '2.0.0', $this->con);
        (new CustomFrontMenu())->postActivation($this->con);

        self::assertSame($afterFirstRun, $this->rows());
        self::assertSame($this->installedSchema, $this->schema());
    }

    private function schema(): string
    {
        $create = (string) $this->con->query('SHOW CREATE TABLE `custom_front_menu_item`')->fetchColumn(1);

        // The counter moves with the rows, not with the schema.
        return (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', $create);
    }

    private function hasColumn(string $name): bool
    {
        $statement = $this->con->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :name');
        $statement->execute(['table' => 'custom_front_menu_item', 'name' => $name]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function rowCount(string $table): int
    {
        return (int) $this->con->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    }

    /**
     * @return array{items: list<array<string, mixed>>, translations: list<array<string, mixed>>}
     */
    private function rows(): array
    {
        $fetch = fn (string $sql): array => array_map(
            static fn (array $row): array => array_map(static fn (mixed $value): mixed => is_numeric($value) ? (int) $value : $value, $row),
            $this->con->query($sql)->fetchAll(\PDO::FETCH_ASSOC),
        );

        return [
            'items' => $fetch('SELECT `id`, `code`, `view`, `view_id`, `new_tab`, `tree_left`, `tree_right`, `tree_level` FROM `custom_front_menu_item` ORDER BY `id`'),
            'translations' => $fetch('SELECT `id`, `locale`, `title`, `url` FROM `custom_front_menu_item_i18n` ORDER BY `id`, `locale`'),
        ];
    }

    /**
     * @param array{translations: list<array<string, mixed>>} $rows
     *
     * @return array<string, ?string>
     */
    private function urls(array $rows): array
    {
        $urls = [];

        foreach ($rows['translations'] as $row) {
            $urls[$row['id'].'/'.$row['locale']] = $row['url'];
        }

        return $urls;
    }
}
