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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Domain\Localization\Service\LangService;

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
        $menuId = (int) ($uriVariables['id'] ?? 0);

        if ($menuId <= 0) {
            throw new NotFoundHttpException('Menu not found');
        }

        $items = $this->treeResolver->resolve($menuId, $this->langService->getLocale() ?? 'en_US');

        if (null === $items) {
            throw new NotFoundHttpException('Menu not found');
        }

        $menu = new CustomFrontMenu();
        $menu->id = $menuId;
        $menu->items = $items;

        return $menu;
    }
}
