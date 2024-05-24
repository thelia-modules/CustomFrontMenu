<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;

class CustomFrontMenuLoadService
{
    public function __construct(private int $COUNT_ID = 1)
    {}

    /**
     * Load all elements from the database recursively to parse them in an array
     * @throws PropelException
     */
    public function loadTableBrowser(CustomFrontMenuItem $parent) : array
    {
        $dataArray = [];
        $descendants = $parent->getChildren();
        foreach ($descendants as $descendant) {
            $newArray = [];
            $content = CustomFrontMenuItemI18nQuery::create()
                ->filterById($descendant->getId())
                ->findByLocale($descendant->getLocale());

            $newArray['depth'] = $descendant->getLevel() - 2;
            $newArray['title'] = $content->getColumnValues('title')[0];
            $newArray['url'] = $content->getColumnValues('url')[0];
            $newArray['id'] = $this->COUNT_ID;
            ++$this->COUNT_ID;

            if ($descendant->hasChildren()) {
                $newArrayChildren = $this->loadTableBrowser($descendant);
                $newArray['childrens'] = $newArrayChildren;
            }
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }
}