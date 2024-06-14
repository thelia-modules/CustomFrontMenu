<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Service\Validator;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Exception;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;

class CustomFrontMenuSaveService
{
    function __construct(
        protected RequestStack $requestStack
    )
    {}

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
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent) : void
    {
        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $adminLocale = $session->getAdminLang()->getLocale();

        foreach ($dataArray as $element) {

            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent)
                ->save();

            if(strtolower($element['type']) === 'url') {
                foreach ($element['url'] as $locale => $url) {
                    $content = new CustomFrontMenuItemI18n();
                    $content->setId($item->getId())
                        ->setLocale($locale);
                    if ($url) {
                        $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($url, $session), FilterType::URL));
                    }
                    $content->save();
                    if(!$element['title'][$locale]) {
                        if ($element['title'][$adminLocale]) {
                            $element['title'][$locale] = $element['title'][$adminLocale];
                        } else {
                            $found = false;
                            foreach ($element['title'] as $value) {
                                if (!$found && !is_null($value)) {
                                    $element['title'][$locale] = $value;
                                    $found = true;
                                }
                            }
                            if (!$found) {
                                $element['title'][$locale] = 'Empty string';
                            }
                        }
                    }
                }
            } elseif (strtolower($element['type']) !== 'empty') {
                $viewIdExploded = explode('-', $element['typeId']);
                $item->setView(ucfirst(Validator::viewIsValidOrEmpty($element['type'])))
                    ->setViewId(intval(end($viewIdExploded)))
                    ->save();
            }

            foreach ($element['title'] as $locale => $title) {
                $content = CustomFrontMenuItemI18nQuery::create()
                    ->filterById($item->getId())
                    ->findOneByLocale($locale);

                if ($content === null) {
                    $content = new CustomFrontMenuItemI18n();
                    $content->setId($item->getId())
                        ->setLocale($locale);
                }

                if(strtolower($element['type']) === 'url' && !isset($element['url'][$locale])) {
                    if (isset($element['url'][$adminLocale])) {
                        $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url'][$adminLocale], $session), FilterType::URL));
                    } else {
                        $found = false;
                        foreach ($element['url'] as $value) {
                            if (!$found && !is_null($value)) {
                                $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($value, $session), FilterType::URL));
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $item->setView('Empty')
                                ->setViewId('')
                                ->save();
                        }
                    }
                }

                $content->setTitle(Validator::completeValidation($title, $session))
                    ->save();
            }



            if (!empty($element['children'])) {
                $this->saveTableBrowser($element['children'], $item);
            }

            $parent->save();
        }
    }

}