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

namespace CustomFrontMenu\Service\Front;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Service\MenuLink;
use CustomFrontMenu\Service\MenuTargetTypes;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Tools\URL;

/**
 * Resolves one menu into the normalised tree the front consumes: {id, title, href, newTab,
 * children}.
 *
 * Same shape as the theme's own NavigationTree, plus `newTab`, so a menu can stand in for a
 * catalogue derived navigation without the templates knowing which one they are rendering: a
 * template that ignores the extra key still renders the menu.
 *
 * Entries whose target is gone or unpublished are dropped rather than rendered: this tree
 * is served publicly, and a menu must never be the thing that reveals a hidden product.
 *
 * An entry without a label of its own in the visitor's language takes the title of its
 * target, read here rather than copied at composition time: renaming a category renames
 * every menu entry that points at it.
 *
 * The menu is rendered on every page, so its cost must not grow with its size: the
 * entries, their translations, the targets of each type with their titles, and their
 * URLs are each read in one batch, then the tree is assembled in memory.
 */
final readonly class MenuTreeResolver
{
    private const TARGET_TITLE = 'menu_target_title';

    /**
     * A menu is addressed by its code, never by its id: the id comes from an autoincrement
     * shared with the entries, so it differs from one installation to the next, while a
     * theme that calls a menu has to keep working after a reinstall.
     *
     * The level check is what makes the code safe to trust: only a menu carries one, so
     * this cannot be pointed at the nested-set root, whose children are the menus
     * themselves, nor at an entry in the middle of a tree.
     *
     * @return list<array<string, mixed>>|null null when no such menu exists
     *
     * @throws PropelException
     */
    public function resolve(string $code, string $locale): ?array
    {
        if ('' === $code) {
            return null;
        }

        $menu = CustomFrontMenuItemQuery::create()->findOneByCode($code);

        if (null === $menu || 1 !== $menu->getLevel()) {
            return null;
        }

        /** @var list<CustomFrontMenuItem> $entries */
        $entries = CustomFrontMenuItemQuery::create()
            ->descendantsOf($menu)
            ->orderByBranch()
            ->find()
            ->getData();

        if ([] === $entries) {
            return [];
        }

        $translations = $this->translationsByEntry($entries);
        $targets = $this->publishedTargets($entries, $locale);

        return $this->assemble($entries, (int) $menu->getLevel(), $translations, $targets, $locale);
    }

    /**
     * Entries come in branch order, so a node always follows its parent and precedes its
     * siblings' subtrees: a stack of open branches is enough to rebuild the nesting.
     *
     * @param list<CustomFrontMenuItem>                                   $entries
     * @param array<int, list<CustomFrontMenuItemI18n>>                   $translations
     * @param array<string, array<int, array{href: string, title: string}>> $targets
     *
     * @return list<array<string, mixed>>
     */
    private function assemble(array $entries, int $menuLevel, array $translations, array $targets, string $locale): array
    {
        $tree = [];
        // One slot per depth below the menu: the children list the next entry at that
        // depth is appended to. A slot is missing when that depth has no rendered parent.
        $open = [0 => &$tree];

        foreach ($entries as $entry) {
            $depth = (int) $entry->getLevel() - $menuLevel - 1;

            // The parent was not rendered, so neither is anything under it.
            if (!isset($open[$depth])) {
                continue;
            }

            for ($closed = $depth + 1; isset($open[$closed]); ++$closed) {
                unset($open[$closed]);
            }

            $node = $this->node($entry, $translations[(int) $entry->getId()] ?? [], $targets, $locale);

            if (null === $node) {
                continue;
            }

            $node['children'] = [];
            $open[$depth][] = $node;
            $open[$depth + 1] = &$open[$depth][array_key_last($open[$depth])]['children'];
        }

        return $tree;
    }

    /**
     * @param list<CustomFrontMenuItemI18n>                                   $translations
     * @param array<string, array<int, array{href: string, title: string}>> $targets
     *
     * @return array<string, mixed>|null null when the entry must not be rendered
     */
    private function node(CustomFrontMenuItem $entry, array $translations, array $targets, string $locale): ?array
    {
        $view = strtolower((string) $entry->getView());
        $viewId = (int) $entry->getViewId();

        // A typed entry stands or falls with its target; a free URL or an untargeted
        // label has nothing to check. A kind no longer offered (the Page module was
        // removed) is a typed entry whose target is gone, not a label.
        if ('' !== $view && $viewId > 0) {
            $target = $targets[$view][$viewId] ?? null;

            if (null === $target) {
                return null;
            }

            // Own label in this language, then the target's title in this language, then
            // the label in another language: a label typed in English only must not hide
            // the French title of the category it points at.
            $title = $this->i18nValue($translations, $locale, 'title', exactLocaleOnly: true);

            return [
                'id' => (int) $entry->getId(),
                'title' => '' !== $title ? $title : ('' !== $target['title'] ? $target['title'] : $this->i18nValue($translations, $locale, 'title')),
                'href' => $target['href'],
                'newTab' => (bool) $entry->getNewTab(),
            ];
        }

        $href = $this->freeUrl($translations, $locale);

        return [
            'id' => (int) $entry->getId(),
            'title' => $this->i18nValue($translations, $locale, 'title'),
            'href' => $href,
            // A label with no link has no tab to open.
            'newTab' => '' !== $href && (bool) $entry->getNewTab(),
        ];
    }

    /**
     * @param list<CustomFrontMenuItem> $entries
     *
     * @return array<int, list<CustomFrontMenuItemI18n>>
     *
     * @throws PropelException
     */
    private function translationsByEntry(array $entries): array
    {
        $translations = [];

        $rows = CustomFrontMenuItemI18nQuery::create()
            ->filterById(array_map(static fn (CustomFrontMenuItem $entry): int => (int) $entry->getId(), $entries))
            ->find();

        foreach ($rows as $translation) {
            $translations[(int) $translation->getId()][] = $translation;
        }

        return $translations;
    }

    /**
     * One query per target type actually used, whatever the number of entries, plus one
     * to warm the rewritten URLs of that type.
     *
     * The title is selected as a column of the same query rather than read through the
     * model: a target with no translation in this locale would otherwise fetch it on its
     * own, one query per entry.
     *
     * @param list<CustomFrontMenuItem> $entries
     *
     * @return array<string, array<int, array{href: string, title: string}>> each published target, by type then id
     *
     * @throws PropelException
     */
    private function publishedTargets(array $entries, string $locale): array
    {
        $queries = MenuTargetTypes::queries();
        $idsByView = [];

        foreach ($entries as $entry) {
            $view = strtolower((string) $entry->getView());
            $viewId = (int) $entry->getViewId();

            if (isset($queries[$view]) && $viewId > 0) {
                $idsByView[$view][$viewId] = $viewId;
            }
        }

        $published = [];

        foreach ($idsByView as $view => $ids) {
            $i18nRelation = ucfirst($view).'I18n';

            $targets = $queries[$view]::create()
                ->filterById(array_values($ids))
                ->filterByVisible(1)
                ->joinI18n($locale, null, Criteria::LEFT_JOIN)
                ->withColumn($i18nRelation.'.Title', self::TARGET_TITLE)
                ->find();

            if (0 === \count($targets)) {
                continue;
            }

            URL::getInstance()->preloadRewrittenUrls(
                $targets->getFirst()->getRewrittenUrlViewName(),
                $locale,
                array_map(static fn ($target): int => (int) $target->getId(), $targets->getData()),
            );

            foreach ($targets as $target) {
                $published[$view][(int) $target->getId()] = [
                    'href' => $target->getUrl($locale),
                    'title' => trim((string) $target->getVirtualColumn(self::TARGET_TITLE)),
                ];
            }
        }

        return $published;
    }

    /**
     * Filtered again here, not only where it was written: rows saved by the 1.x screen
     * went through FILTER_SANITIZE_URL, which leaves a `javascript:` URL untouched, and
     * this value is rendered on every page and served by a public API.
     *
     * @param list<CustomFrontMenuItemI18n> $translations
     */
    private function freeUrl(array $translations, string $locale): string
    {
        return MenuLink::filter($this->i18nValue($translations, $locale, 'url')) ?? '';
    }

    /**
     * Locale, then en_US, then whatever exists: an entry with no translation in the
     * visitor's language still has to render.
     *
     * The back-office form writes one row per active language, so a language left blank
     * exists in the table with an empty value. An empty value is not a translation: it
     * must not win over, nor block, the fallback.
     *
     * @param list<CustomFrontMenuItemI18n> $translations
     */
    private function i18nValue(array $translations, string $locale, string $column, bool $exactLocaleOnly = false): string
    {
        $english = '';
        $any = '';

        foreach ($translations as $translation) {
            $value = trim((string) ('title' === $column ? $translation->getTitle() : $translation->getUrl()));

            if ('' === $value) {
                continue;
            }

            if ($translation->getLocale() === $locale) {
                return $value;
            }

            if ($exactLocaleOnly) {
                continue;
            }

            if ('en_US' === $translation->getLocale()) {
                $english = $value;
            }

            if ('' === $any) {
                $any = $value;
            }
        }

        return '' !== $english ? $english : $any;
    }
}
