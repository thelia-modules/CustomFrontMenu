<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuContent;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItem;

class CusomFrontMenuSaveService
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
            $content = new CustomFrontMenuContent();
            $content->setTitle($element['title']);
            $content->setUrl($element['url']);
            $content->setMenuItem($item->getId());
            $content->save();
            if (isset($element['childrens']) && $element['childrens'] !== []) {
                $this->saveTableBrowser($element['childrens'], $item);
            }
        }
    }

}