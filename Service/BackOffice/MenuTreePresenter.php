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
use CustomFrontMenu\Service\MenuTargetTypes;
use Propel\Runtime\Exception\PropelException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Shapes one menu into what the composition screen displays: a title, what the entry
 * points at in words, and whether that target still resolves.
 *
 * A missing or unpublished target is reported rather than hidden, which is the opposite
 * of the front: the person composing the menu is exactly who needs to know.
 */
final readonly class MenuTreePresenter
{
    private const DOMAIN = 'customfrontmenu.bo.default-twig';

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
            $target = $this->target($child, $locale);
            $ownTitle = $this->ownTitle($child, $locale);

            $nodes[] = [
                'id' => (int) $child->getId(),
                // Same default as the front: no label of its own means the target's title.
                'title' => '' !== $ownTitle ? $ownTitle : $this->untitledUnless($target['title']),
                'depth' => $depth,
                'target' => $target,
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
        return $this->untitledUnless($this->ownTitle($item, $locale));
    }

    /**
     * The title an entry shows when it has no label of its own: that of its target, if the
     * target still resolves. Empty otherwise.
     *
     * @throws PropelException
     */
    public function targetTitle(CustomFrontMenuItem $item, string $locale): string
    {
        return $this->target($item, $locale)['title'];
    }

    /**
     * @throws PropelException
     */
    private function ownTitle(CustomFrontMenuItem $item, string $locale): string
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

        return $fallback;
    }

    private function untitledUnless(string $title): string
    {
        return '' !== $title ? $title : $this->translator->trans('Untitled', [], self::DOMAIN);
    }

    /**
     * What the entry points at, and whether the front will render it.
     *
     * `title` is what the front falls back to when the entry has no label of its own:
     * the target's title while the target resolves, nothing otherwise.
     *
     * @return array{kind: string, label: string, title: string, ok: bool}
     *
     * @throws PropelException
     */
    private function target(CustomFrontMenuItem $item, string $locale): array
    {
        $view = strtolower((string) $item->getView());
        $viewId = (int) $item->getViewId();

        if ('' === $view || $viewId <= 0) {
            $url = $this->url($item, $locale);

            if ('' !== $url) {
                return ['kind' => 'url', 'label' => $url, 'title' => '', 'ok' => true];
            }

            return [
                'kind' => 'none',
                'label' => $this->translator->trans('No target', [], self::DOMAIN),
                'title' => '',
                'ok' => true,
            ];
        }

        $queryClass = MenuTargetTypes::queries()[$view] ?? null;
        // A kind no longer offered (the Page module was removed) leaves the target as
        // unreachable as a deleted one, and the front drops the entry the same way.
        $target = null === $queryClass ? null : $queryClass::create()->findPk($viewId);

        if (null === $target) {
            return [
                'kind' => $view,
                'label' => $this->translator->trans('Deleted target (#%id%)', ['%id%' => $viewId], self::DOMAIN),
                'title' => '',
                'ok' => false,
            ];
        }

        $target->setLocale($locale);
        $label = (string) $target->getTitle();

        // Unpublished: the entry is dropped from the front, so say so here.
        if (!$target->getVisible()) {
            return [
                'kind' => $view,
                'label' => $this->translator->trans('%title% (offline)', ['%title%' => $label], self::DOMAIN),
                'title' => '',
                'ok' => false,
            ];
        }

        return ['kind' => $view, 'label' => $label, 'title' => $label, 'ok' => true];
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
