<?php

namespace CustomFrontMenu\Controller\Front;

use CustomFrontMenu\Service\CustomFrontMenuService;
use CustomFrontMenu\Service\CustomFrontMenuLoadService;
use OpenAPI\Controller\Front\BaseFrontOpenApiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\HttpFoundation\Response as ResponseAlias;
use Thelia\Core\HttpFoundation\JsonResponse;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\Get;
use OpenApi\Service\OpenApiService;
use Thelia\Core\Thelia;

#[Route('open_api/custom-front-menu', name: 'custom_front_menu_api')]
class CustomFrontMenuAPIController extends BaseFrontOpenApiController
{
    function __construct() 
    {}
    #[Route('/{id}', methods: ['GET'])]
    /**
     * @OA\Get(
     *    path="/custom-front-menu/{id}",
     *    summary="Get a menu and its children by id",
     *    description="Get a menu and its children by id",
     *    tags={"Custom Front Menu"},
     *    @OA\Parameter(
     *       name="id",
     *       in="path",
     *       description="The id of the menu",
     *       required=true,
     *    ),
     *   @OA\Response(
     *      response=200,
     *      description="The menu",
     *    ),
     *   @OA\Response(
     *     response=204,
     *     description="Menu has no children",
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Menu not found",
     *    ),
     * )
     */
    public function getMenuAndChildrenById(int $id, CustomFrontMenuLoadService $customFrontMenuLoadService, CustomFrontMenuService $customFornMenuService) : JsonResponse
    {
        $menu = $customFrontMenuLoadService->loadTableBrowser($customFornMenuService->getMenu($id));
        if ($menu === null) {
            return OpenApiService::jsonResponse('Menu not found', Response::HTTP_NOT_FOUND);
        }
        if (count($menu) === 0) {
            return OpenApiService::jsonResponse('Menu has no children', Response::HTTP_NO_CONTENT);
        }
        return OpenApiService::jsonResponse($menu, Response::HTTP_OK);
    }
}