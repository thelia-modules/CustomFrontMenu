<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Service\Validator;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemQuery;
use Exception;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CustomFrontMenuSaveService
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
                    $content = new CustomFrontMenuItemI18n();
                    $content->setId($item->getId());
                    $content->setLocale($locale);
                    if (isset($url)) {
                        $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($url, $session), FilterType::URL));
                    }
                    $content->save();
                    if(!isset($element['title'][$locale])) {
                        $langLocale = $session->get('thelia.current.admin_lang')->getLocale();
                        if (isset($element['title'][$langLocale])) {
                            $element['title'][$locale] = $element['title'][$langLocale];
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
            } elseif ($element['type'] !== '') {
                $item->setView(ucfirst(Validator::viewIsValidOrEmpty($element['type'])));
                $viewIdExploded = explode('-', $element['url']['en_US']);
                $item->setViewId(intval(end($viewIdExploded)));
                $item->save();
            }

            if($element['title'])

            foreach ($element['title'] as $locale => $title) {
                $content = CustomFrontMenuItemI18nQuery::create()
                    ->filterById($item->getId())
                    ->findOneByLocale($locale);

                if ($content === null) {
                    $content = new CustomFrontMenuItemI18n();
                    $content->setId($item->getId());
                    $content->setLocale($locale);
                }

                if(strtolower($element['type']) === 'url' && !isset($element['url'][$locale])) {
                    $langLocale = $session->get('thelia.current.admin_lang')->getLocale();
                    if (isset($element['url'][$langLocale])) {
                        $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url'][$langLocale], $session), FilterType::URL));
                    } else {
                        $found = false;
                        foreach ($element['url'] as $value) {
                            if (!$found && !is_null($value)) {
                                $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($value, $session), FilterType::URL));
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $item->setView('Empty');
                            $item->setViewId('');
                            $item->save();
                        }
                    }
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