<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Service\Validator;
use Exception;
use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CFMSaveService implements CFMSaveInterface
{
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

            foreach ($element['title'] as $locale => $title) {
                $content = new CustomFrontMenuItemI18n();
                $content->setTitle(Validator::completeValidation($title, $session));
                if (isset($element['url']) && isset($element['url'][$locale])) {
                    $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url'][$locale], $session), FilterType::URL));
                }
                else{
                    $content->setUrl("");
                }
                $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url'][$locale], $session), FilterType::URL));
                $content->setId($item->getId());
                $content->setLocale($locale);
                $content->save();
            }

            if (isset($element['children']) && $element['children'] !== []) {
                $this->saveTableBrowser($element['children'], $item, $session);
            }
            $parent->save();
        }
    }

}