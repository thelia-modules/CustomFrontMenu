<?php

namespace CustomFrontMenu\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;


class CustomFrontMenuService
{
    public function __construct()
    {}

    /**
     * @throws PropelException
     */
    public function getRoot() : CustomFrontMenuItem
    {
        if (CustomFrontMenuItemQuery::create()->findRoot() === null) {
            $root = new CustomFrontMenuItem();
            $root->makeRoot();
            $root->save();
        } else {
            $root = CustomFrontMenuItemQuery::create()->findRoot();
        }
        return $root;
    }

    /**
     * @throws PropelException
     */
    public function addMenu(CustomFrontMenuItem $root, string $menuName, SessionInterface $session) : int
    {
        $item = new CustomFrontMenuItem();
        $item->insertAsLastChildOf($root);
        $item->save();
        $content = new CustomFrontMenuItemI18n();
        $content->setTitle(Validator::completeValidation($menuName, $session));
        $content->setId($item->getId());
        $content->setLocale('en_US');
        $content->save();
        return $item->getId();
    }

    public function deleteMenu(int $menuId)  : void
    {
        CustomFrontMenuItemI18nQuery::create()->findById($menuId)->delete();
        CustomFrontMenuItemQuery::create()->findById($menuId)->delete();
    }

    public function getMenu(int $menuId) : ?CustomFrontMenuItem
    {
        return CustomFrontMenuItemQuery::create()->findOneById($menuId);
    }
}