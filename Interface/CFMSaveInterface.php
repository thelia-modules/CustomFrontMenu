<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

interface CFMSaveInterface
{
    /**
     * Delete all the items currently in database for the menu to save
     * @param int $menuId The id of the menu to delete
     * @return CustomFrontMenuItem The menu after deletion
     * @throws PropelException
     */
    public function deleteSpecificItems(int $menuId) :  CustomFrontMenuItem;

    /**
     * Save all elements from an array recursively to the database
     * @param array $dataArray The data to save
     * @param CustomFrontMenuItem $parent The parent item in database
     * @param SessionInterface $session The user session
     * @throws PropelException
     */
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent, SessionInterface $session): void;
}