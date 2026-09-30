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

namespace CustomFrontMenu\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CustomFrontMenu\Api\Resource\CustomFrontMenu;
use CustomFrontMenu\Service\Front\MenuTreeResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\LangQuery;

/**
 * @implements ProviderInterface<CustomFrontMenu>
 */
final readonly class CustomFrontMenuProvider implements ProviderInterface
{
    public function __construct(
        private MenuTreeResolver $treeResolver,
        private LangService $langService,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomFrontMenu
    {
        $code = trim((string) ($uriVariables['code'] ?? ''));

        if ('' === $code) {
            throw new NotFoundHttpException('Menu not found');
        }

        $items = $this->treeResolver->resolve($code, $this->locale($context));

        if (null === $items) {
            throw new NotFoundHttpException('Menu not found');
        }

        $menu = new CustomFrontMenu();
        $menu->code = $code;
        $menu->items = $items;

        return $menu;
    }

    /**
     * `?locale=` first, as the core front API reads it: a headless client is stateless, so
     * the session language it would otherwise fall back to is always the shop's default.
     *
     * @param array<string, mixed> $context
     */
    private function locale(array $context): string
    {
        $request = $context['request'] ?? null;
        $requested = $request instanceof Request ? $request->query->get('locale') : null;

        if (\is_string($requested) && '' !== $requested
            && null !== LangQuery::create()->filterByActive(true)->findOneByLocale($requested)) {
            return $requested;
        }

        return $this->langService->getLocale() ?? 'en_US';
    }
}
