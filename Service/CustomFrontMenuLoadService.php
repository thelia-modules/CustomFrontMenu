<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuContentQuery;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use Propel\Runtime\Exception\PropelException;

class CustomFrontMenuLoadService
{
    public function __construct(private int $COUNT_ID = 1)
    {}

    /**
     * Load all elements from the database recursively to parse them in an array
     * @throws PropelException
     */
    public function loadTableBrowser(array & $dataArray, CustomFrontMenuItem $parent) : void
    {
        $descendants = $parent->getChildren();
        foreach ($descendants as $descendant) {
            $newArray = [];
            $content = CustomFrontMenuContentQuery::create()->findByMenuItem($descendant->getId());
            $newArray['depth'] = $descendant->getLevel() - 1;

            $newArray['title'] = $content->getColumnValues('title')[0];
            $newArray['url'] = $content->getColumnValues('url')[0];
            $newArray['id'] = $this->COUNT_ID;
            ++$this->COUNT_ID;

            if ($descendant->hasChildren()) {
                $newArrayChildrens = [];
                $this->loadTableBrowser($newArrayChildrens, $descendant);
                $newArray['childrens'] = $newArrayChildrens;
            }
            $dataArray[] = $newArray;
        }
    }
}