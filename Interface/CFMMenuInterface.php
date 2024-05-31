<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

interface CFMMenuInterface
{
    /**
     * Return the menu root from the database.
     * If this root doesn't exist, it's created.
     */
    public function getRoot() : CustomFrontMenuItem;

    /**
     * @param string $menuName The name of the menu to add
     * @param SessionInterface $session The user session
     * @return int The menu id of the created menu in the database
     */
    public function addMenu(CustomFrontMenuItem $root, string $menuName, SessionInterface $session) : int;

    /**
     * @param int $menuId The id of the menu to delete
     */
    public function deleteMenu(int $menuId) : void;

    /**
     * @param int $menuId The id of the menu to get
     */
    public function getMenu(int $menuId) : ?CustomFrontMenuItem;
}