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
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace CustomFrontMenu\Twig;

use CustomFrontMenu\Service\Front\MenuTreeResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig replacement for the Smarty {CustomFrontMenuPlugin menu_id=x} function.
 *
 * The Environment is passed per call rather than injected: injecting it into an
 * extension it is itself registered on is a circular reference.
 */
final class CustomFrontMenuExtension extends AbstractExtension
{
    private const TEMPLATE = '@CustomFrontMenuModule/front/menu.html.twig';

    public function __construct(
        private readonly MenuTreeResolver $treeResolver,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'custom_front_menu',
                $this->render(...),
                ['needs_environment' => true, 'is_safe' => ['html']],
            ),
        ];
    }

    public function render(Environment $twig, int $menuId, ?string $locale = null): string
    {
        $menuItems = $this->treeResolver->resolve($menuId, $locale ?? $this->locale());

        // A theme asking for a menu that no longer exists gets nothing, not an
        // exception: a deleted menu must not take the whole page down.
        if (null === $menuItems) {
            return '';
        }

        return $twig->render(self::TEMPLATE, ['menuItems' => $menuItems]);
    }

    private function locale(): string
    {
        /** @var Session|null $session */
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        return $session?->getLang()?->getLocale() ?? 'en_US';
    }
}
