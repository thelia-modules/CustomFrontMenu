<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

interface CFMMenuInterface
{
    public function getRoot() : CustomFrontMenuItem;

    public function addMenu(CustomFrontMenuItem $root, string $menuName, SessionInterface $session) : int;

    public function deleteMenu(int $menuId) : void;

    public function getMenu(int $menuId) : CustomFrontMenuItem;
}