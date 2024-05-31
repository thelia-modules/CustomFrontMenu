<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;

class CFMLoadService implements CFMLoadInterface
{
    public function __construct(private int $COUNT_ID = 1)
    {}

    public function loadSelectMenu(CustomFrontMenuItem $root) : array
    {
        $descendants = $root->getChildren();
        $dataArray = [];
        foreach ($descendants as $descendant) {
            $newArray = [];
            $newArray['id'] = 'menu-selected-'.$descendant->getId();
            $content = CustomFrontMenuItemI18nQuery::create()
                ->filterById($descendant->getId())
                ->findByLocale('en_US');

            $newArray['title'] = $content->getColumnValues('title')[0];
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }

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
                $newArray['children'] = $this->loadTableBrowser($descendant);
            }
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }
}