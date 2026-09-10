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
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;

/**
 * Resolves one menu into the normalised tree the front consumes: {id, title, href, children}.
 *
 * Same shape as the theme's own NavigationTree, so a menu can stand in for a catalogue
 * derived navigation without the templates knowing which one they are rendering.
 *
 * Entries whose target is gone or unpublished are dropped rather than rendered: this tree
 * is served publicly, and a menu must never be the thing that reveals a hidden product.
 */
final readonly class MenuTreeResolver
{
    private const TARGET_QUERIES = [
        'brand' => BrandQuery::class,
        'category' => CategoryQuery::class,
        'content' => ContentQuery::class,
        'folder' => FolderQuery::class,
        'product' => ProductQuery::class,
    ];

    /**
     * @return list<array<string, mixed>>|null null when no such menu exists
     *
     * @throws PropelException
     */
    public function resolve(int $menuId, string $locale): ?array
    {
        $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);

        if (null === $menu) {
            return null;
        }

        return $this->branch($menu, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws PropelException
     */
    private function branch(CustomFrontMenuItem $parent, string $locale): array
    {
        $nodes = [];

        foreach ($parent->getChildren() as $child) {
            $node = $this->node($child, $locale);

            if (null === $node) {
                continue;
            }

            $node['children'] = $child->hasChildren() ? $this->branch($child, $locale) : [];
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>|null null when the entry must not be rendered
     *
     * @throws PropelException
     */
    private function node(CustomFrontMenuItem $item, string $locale): ?array
    {
        $view = strtolower((string) $item->getView());
        $viewId = (int) $item->getViewId();

        // A typed entry stands or falls with its target; a free URL or an untargeted
        // label has nothing to check.
        if (isset(self::TARGET_QUERIES[$view]) && $viewId > 0) {
            $href = $this->publishedTargetUrl($view, $viewId, $locale);

            if (null === $href) {
                return null;
            }

            return [
                'id' => (int) $item->getId(),
                'title' => $this->title($item, $locale),
                'href' => $href,
            ];
        }

        return [
            'id' => (int) $item->getId(),
            'title' => $this->title($item, $locale),
            'href' => $this->freeUrl($item, $locale),
        ];
    }

    /**
     * @throws PropelException
     */
    private function publishedTargetUrl(string $view, int $viewId, string $locale): ?string
    {
        $queryClass = self::TARGET_QUERIES[$view];

        $target = $queryClass::create()
            ->filterByVisible(1)
            ->findPk($viewId);

        return $target?->getUrl($locale);
    }

    /**
     * @throws PropelException
     */
    private function title(CustomFrontMenuItem $item, string $locale): string
    {
        return $this->i18nValue($item, $locale, 'title');
    }

    /**
     * @throws PropelException
     */
    private function freeUrl(CustomFrontMenuItem $item, string $locale): string
    {
        return $this->i18nValue($item, $locale, 'url');
    }

    /**
     * Locale, then en_US, then whatever exists: an entry with no translation in the
     * visitor's language still has to render.
     *
     * @throws PropelException
     */
    private function i18nValue(CustomFrontMenuItem $item, string $locale, string $column): string
    {
        $translations = CustomFrontMenuItemI18nQuery::create()->findById($item->getId());

        $fallback = '';

        foreach ($translations as $translation) {
            $value = (string) ('title' === $column ? $translation->getTitle() : $translation->getUrl());

            if ($translation->getLocale() === $locale) {
                return $value;
            }

            if ('' === $fallback || 'en_US' === $translation->getLocale()) {
                $fallback = $value;
            }
        }

        return $fallback;
    }
}
