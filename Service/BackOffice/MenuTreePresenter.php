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

namespace CustomFrontMenu\Service\BackOffice;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;

/**
 * Shapes one menu into what the composition screen displays: a title, what the entry
 * points at in words, and whether that target still resolves.
 *
 * A missing or unpublished target is reported rather than hidden, which is the opposite
 * of the front: the person composing the menu is exactly who needs to know.
 */
final readonly class MenuTreePresenter
{
    private const TARGET_QUERIES = [
        'brand' => BrandQuery::class,
        'category' => CategoryQuery::class,
        'content' => ContentQuery::class,
        'folder' => FolderQuery::class,
        'product' => ProductQuery::class,
    ];

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws PropelException
     */
    public function tree(CustomFrontMenuItem $parent, string $locale, int $depth = 0): array
    {
        $nodes = [];

        foreach ($parent->getChildren() as $child) {
            $nodes[] = [
                'id' => (int) $child->getId(),
                'title' => $this->title($child, $locale),
                'depth' => $depth,
                'target' => $this->target($child, $locale),
                'children' => $child->hasChildren() ? $this->tree($child, $locale, $depth + 1) : [],
            ];
        }

        return $nodes;
    }

    /**
     * @throws PropelException
     */
    public function title(CustomFrontMenuItem $item, string $locale): string
    {
        $translations = CustomFrontMenuItemI18nQuery::create()->findById($item->getId());
        $fallback = '';

        foreach ($translations as $translation) {
            $title = (string) $translation->getTitle();

            if ($translation->getLocale() === $locale && '' !== $title) {
                return $title;
            }

            if ('' === $fallback) {
                $fallback = $title;
            }
        }

        return '' !== $fallback
            ? $fallback
            : $this->translator->trans('Untitled', [], 'customfrontmenu.bo.default-twig');
    }

    /**
     * What the entry points at, and whether the front will render it.
     *
     * @return array{kind: string, label: string, ok: bool}
     *
     * @throws PropelException
     */
    private function target(CustomFrontMenuItem $item, string $locale): array
    {
        $domain = 'customfrontmenu.bo.default-twig';
        $view = strtolower((string) $item->getView());
        $viewId = (int) $item->getViewId();

        if (!isset(self::TARGET_QUERIES[$view]) || $viewId <= 0) {
            $url = $this->url($item, $locale);

            if ('' !== $url) {
                return ['kind' => 'url', 'label' => $url, 'ok' => true];
            }

            return [
                'kind' => 'none',
                'label' => $this->translator->trans('No target', [], $domain),
                'ok' => true,
            ];
        }

        $queryClass = self::TARGET_QUERIES[$view];
        $target = $queryClass::create()->findPk($viewId);

        if (null === $target) {
            return [
                'kind' => $view,
                'label' => $this->translator->trans('Deleted target (#%id%)', ['%id%' => $viewId], $domain),
                'ok' => false,
            ];
        }

        $target->setLocale($locale);
        $label = (string) $target->getTitle();

        // Unpublished: the entry is dropped from the front, so say so here.
        if (!$target->getVisible()) {
            return [
                'kind' => $view,
                'label' => $this->translator->trans('%title% (offline)', ['%title%' => $label], $domain),
                'ok' => false,
            ];
        }

        return ['kind' => $view, 'label' => $label, 'ok' => true];
    }

    /**
     * @throws PropelException
     */
    private function url(CustomFrontMenuItem $item, string $locale): string
    {
        $translations = CustomFrontMenuItemI18nQuery::create()->findById($item->getId());
        $fallback = '';

        foreach ($translations as $translation) {
            $url = (string) $translation->getUrl();

            if ($translation->getLocale() === $locale && '' !== $url) {
                return $url;
            }

            if ('' === $fallback) {
                $fallback = $url;
            }
        }

        return $fallback;
    }
}
