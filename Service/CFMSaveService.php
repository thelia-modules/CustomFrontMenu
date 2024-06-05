<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Interface\CFMMenuInterface;
use CustomFrontMenu\Service\Validator;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Exception;
use Propel\Runtime\Exception\PropelException;
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


    /**
     * Save all elements from an array recursively to the database
     * @throws PropelException
     * @throws Exception
     */
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent, SessionInterface $session) : void
    {
        foreach ($dataArray as $element) {

            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent);

            $item->save();

            if(strtolower($element['type']) === 'url') {
                foreach ($element['url'] as $locale => $url) {
                    //if (filter_var($url, FILTER_VALIDATE_URL))
                    $content = new CustomFrontMenuItemI18n();
                    $content->setId($item->getId());
                    $content->setLocale($locale);
                    $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($url, $session), FilterType::URL));
                    $content->save();
                }
            } else {
                $item->setView(ucfirst(Validator::viewIsValid($element['type'])));
                $item->setViewId(intval($element['url'][$session->get('thelia.current.lang')->getLocale()]));
                $item->save();
            }

            foreach ($element['title'] as $locale => $title) {
                $content = CustomFrontMenuItemI18nQuery::create()
                    ->filterById($item->getId())
                    ->findOneByLocale($locale);

                if ($content === null) {
                    $content = new CustomFrontMenuItemI18n();
                    $content->setId($item->getId());
                    $content->setLocale($locale);
                    $content->setTitle(Validator::completeValidation($title, $session));
                }

                $content->setTitle(Validator::completeValidation($title, $session));
                $content->save();
            }

            if (isset($element['children']) && $element['children'] !== []) {
                $this->saveTableBrowser($element['children'], $item, $session);
            }

            $parent->save();
        }
    }

}