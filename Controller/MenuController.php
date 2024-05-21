<?php

namespace CustomFrontMenu\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    /**
     * @Route("/admin/module/CustomFrontMenu/save", name="admin.responseform", methods={"POST"})
     */
    public function saveMenuItems() : RedirectResponse
    {
        if (null !== $this->checkAuth(
            AdminResources::MODULE,
            ['customfrontmenu'],
            AccessManager::UPDATE
        )) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));;
        }
        $rep = $this->getRequest()->get('menuData');
        $array = json_decode($rep, true);
        //$this->testDisplay($array);

        $this->getSession()->getFlashBag()->add('success', 'This menu has been successfully saved !');
        
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu'));
    }

    /**
     * @Route("/admin/module/CustomFrontMenu/clearFlashes", name="admin.clearflashes", methods={"GET"})
     */
    public function clearFlashes() : void
    {
        echo '<script>';
        echo 'console.log("Hello, console from PHP!");';
        echo '</script>';
        $this->getSession()->getFlashBag()->clear();
    }

    /**
     * This function will be deleted, this is for testing only
     */
    public function testDisplay($array) {
        foreach ($array as $parent) {
            echo '<strong>' . $parent['title'] . '</strong> -> ' . $parent['url'] . '<br>';
            if (isset($parent['childrens'])) {
                foreach ($parent['childrens'] as $child1) {
                    echo '. . . . . . |------- <strong>' . $child1['title'] . "</strong> -> " . $child1['url'] . '<br>';
                    if (isset($child1['childrens'])) {
                        foreach ($child1['childrens'] as $child2) {
                            echo '. . . . . . . . . . . . |------- <strong>' . $child2['title'] . "</strong> -> " . $child2['url'] . '<br>';
                            if (isset($child2['childrens'])) {
                                foreach ($child2['childrens'] as $child3) {
                                    echo '. . . . . . . . . . . . . . . . . . . . . |------- <strong>' . $child3['title'] . "</strong> -> " . $child3['url'] . '<br>';
                                    
                                }
                            }
                        }
                    }
                }
            }
        }
        //print_r($array);
        die;
    }

    // Load the menu items
    public function loadMenuItems() : void
    {
        $stub = 
        [
            [
                'id' => 1, 'title' => 'Accueil', 'url' => 'https://localhost:11111', 'depth' => 0, 'childrens' => 
                [
                    [
                        'id' => 2, 'title' => 'A propos de nous', 'url' => 'https://localhost:12222', 'depth' => 1
                    ],
                    [
                        'id' => 4, 'title' => 'Nos valeurs', 'url' => 'https://localhost:13333', 'depth' => 1
                    ]
                ]
            ],
            ['id' => 10, 'title' => 'Produits', 'url' => 'https://localhost:33333', 'depth' => 0, 'childrens' =>
                [
                    [
                        'id' => 11, 'title' => 'Casques', 'url' => 'https://localhost:12222', 'depth' => 1, 'childrens' =>
                        [
                            [
                                'id' => 12, 'title' => 'Casque classique', 'url' => 'https://localhost:12333', 'depth' => 2
                            ],
                            [
                                'id' => 14, 'title' => 'Casque moderne', 'url' => 'https://localhost:12333', 'depth' => 2
                            ],
                            [
                                'id' => 15, 'title' => 'Casque renforcé', 'url' => 'https://localhost:12333', 'depth' => 2
                            ]
                        ]
                        ],
                        [
                        'id' => 16, 'title' => 'Vestes', 'url' => 'https://localhost:12222', 'depth' => 1, 'childrens' =>
                        [
                            [
                                'id' => 17, 'title' => 'Cuir synnthétique', 'url' => 'https://localhost:12333', 'depth' => 2
                            ],
                            [
                                'id' => 18, 'title' => 'Cuir de bovin', 'url' => 'https://localhost:12333', 'depth' => 2
                            ],
                            [
                                'id' => 19, 'title' => 'Cuir de bovidé', 'url' => 'https://localhost:12333', 'depth' => 2
                            ]
                        ]
                        ],
                        [
                        'id' => 20, 'title' => 'Accessoires', 'url' => 'https://localhost:12222', 'depth' => 1, 'childrens' =>
                        [
                            [
                                'id' => 21, 'title' => 'Gants', 'url' => 'https://localhost:12333', 'depth' => 2, 'childrens' =>
                                [
                                    [
                                        'id' => 23, 'title' => 'Cuir synthétique', 'url' => 'https://localhost:12333', 'depth' => 3
                                    ],
                                    [
                                        'id' => 24, 'title' => 'Coton renforcé', 'url' => 'https://localhost:12333', 'depth' => 3
                                    ],
                                ]
                            ],
                            [
                                'id' => 22, 'title' => 'Porte-clé', 'url' => 'https://localhost:12333', 'depth' => 2
                            ]
                        ]
                    ]
                ]
            ],
            ['id' => 45, 'title' => 'Contactez-nous', 'url' => 'https://localhost:33333', 'depth' => 0]
        ];

        $dataToLoad = json_encode($stub);

        setcookie('menuItems', $dataToLoad);
    }
}