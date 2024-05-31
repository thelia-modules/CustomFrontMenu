<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Interface\CFMLoadInterface;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;

class CFMLoadService implements CFMLoadInterface
{
    public function __construct(private int $COUNT_ID = 1)
    {}

    /**
     * Load the different menu names
     * @throws PropelException
     */
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
            $I18nMenus = CustomFrontMenuItemI18nQuery::create()
                ->findById($descendant->getId());

            if (count($I18nMenus) <= 0){
                throw new PropelException('No content found for the given id:' . $descendant->getId());
            }

            foreach ($I18nMenus as $I18nMenu) {
                $newArray['title'][$I18nMenu->getLocale()] = $I18nMenu->getTitle();
                $newArray['url'][$I18nMenu->getLocale()] = $I18nMenu->getUrl();
            }


            $newArray['depth'] = $descendant->getLevel() - 2;
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