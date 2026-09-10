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

namespace CustomFrontMenu\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use CustomFrontMenu\Api\State\CustomFrontMenuProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One composed menu, as the normalised tree the front renders.
 *
 * Read-only: composing a menu is a back-office operation, done through the module's
 * configuration screen, not through this resource.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/custom-front-menus/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            provider: CustomFrontMenuProvider::class,
        ),
    ],
)]
class CustomFrontMenu
{
    public const GROUP_FRONT_READ = 'custom_front_menu:front:read';

    #[Groups([self::GROUP_FRONT_READ])]
    public int $id = 0;

    /**
     * Nodes of {id, title, href, children}, children nested to any depth.
     *
     * @var list<array<string, mixed>>
     */
    #[Groups([self::GROUP_FRONT_READ])]
    public array $items = [];
}
