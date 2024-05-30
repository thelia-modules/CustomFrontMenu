<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

interface CFMSaveInterface
{
    public function deleteSpecificItems(int $menuId) :  CustomFrontMenuItem;
    
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent, SessionInterface $session, string $locale): void;
}