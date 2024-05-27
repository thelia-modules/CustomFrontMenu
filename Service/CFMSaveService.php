<?php

namespace CustomFrontMenu\Service;

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
     */
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent) : void
    {
        foreach ($dataArray as $element) {
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent);

            $item->save();

            $content = new CustomFrontMenuItemI18n();
            $content->setTitle($element['title']);
            $content->setUrl($element['url']);
            $content->setId($item->getId());
            $content->setLocale('en_US');
            $content->save();

            if (isset($element['childrens']) && $element['childrens'] !== []) {
                $this->saveTableBrowser($element['childrens'], $item);
            }
            $parent->save();
        }
    }

}