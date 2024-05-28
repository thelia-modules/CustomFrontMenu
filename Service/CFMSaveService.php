<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Service\Validator;
use Exception;
use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItem;

class CFMSaveService implements CFMSaveInterface
{
    public function __construct()
    {}

    /**
     * Save all elements from an array recursively to the database
     * @throws PropelException
     * @throws Exception
     */
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent) : void
    {
        foreach ($dataArray as $element) {
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent);

            $item->save();

            $content = new CustomFrontMenuItemI18n();
            $content->setTitle(Validator::completeValidation($element['title']));
            $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url']), FilterType::URL));
            $content->setId($item->getId());
            $content->setLocale('en_US');
            $content->save();

            if (isset($element['children']) && $element['children'] !== []) {
                $this->saveTableBrowser($element['children'], $item);
            }
            $parent->save();
        }
    }

}