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

namespace CustomFrontMenu\Twig;

use CustomFrontMenu\Service\Front\MenuTreeResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig replacement for the Smarty {CustomFrontMenuPlugin menu_id=x} function.
 *
 * Called as custom_front_menu('header'): a menu is addressed by its code, which the shop
 * owner chooses and which survives a reinstall, not by its id.
 *
 * Returns the tree, not markup. A navigation is where a theme's own layout, breakpoints
 * and interaction live, so the module has no business shipping elements and classes that
 * the integrator would then have to fight. The module answers data; the theme writes the
 * markup it wants.
 */
final class CustomFrontMenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuTreeResolver $treeResolver,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('custom_front_menu', $this->menu(...)),
        ];
    }

    /**
     * Nodes of {id, title, href, children}, children nested to any depth.
     *
     * An unknown code answers an empty list rather than raising: a {% for %} over it
     * renders nothing, and a menu deleted in the back-office must not take a page down.
     *
     * @return list<array<string, mixed>>
     */
    public function menu(string $code, ?string $locale = null): array
    {
        return $this->treeResolver->resolve($code, $locale ?? $this->locale()) ?? [];
    }

    private function locale(): string
    {
        /** @var Session|null $session */
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        return $session?->getLang()?->getLocale() ?? 'en_US';
    }
}
