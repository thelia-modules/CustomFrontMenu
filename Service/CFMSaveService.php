<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Service\Validator;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CFMSaveService implements CFMSaveInterface
{
    public function deleteSpecificItems(int $menuId) :  CustomFrontMenuItem
    {
        $menu = CustomFrontMenuItemQuery::create()->findOneById($menuId);

        $descendants = $menu->getDescendants();
        foreach ($descendants as $descendant) {
            CustomFrontMenuItemI18nQuery::create()->findById($descendant->getId())->delete();
        }
        $menu->deleteDescendants();

        $menu->save();
        return $menu;
    }

    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent, SessionInterface $session) : void
    {
        foreach ($dataArray as $element) {

            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent);

            $item->save();

            $content = new CustomFrontMenuItemI18n();
            $content->setTitle(Validator::completeValidation($element['title'], $session));
            $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url'], $session), FilterType::URL));
            $content->setId($item->getId());
            $locale = $session->get('_locale', 'en_US');
            $content->setLocale($locale);
            $content->save();

            if (isset($element['children']) && $element['children'] !== []) {
                $this->saveTableBrowser($element['children'], $item, $session);
            }

            $parent->save();
        }
    }

}