<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Propel\Runtime\Exception\PropelException;

class CFMLoadService implements CFMLoadInterface
{
    public function __construct(private int $COUNT_ID = 1)
    {}

    /**
     * Load the different menu names
     */
    public function loadSelectMenu(CustomFrontMenuItem $root) : array
    {
        $descendants = $root->getChildren();
        $dataArray = [];
        foreach ($descendants as $descendant) {
            $newArray = [];
            $newArray['id'] = 'menu-selected-' . $descendant->getId();
            $content = CustomFrontMenuItemI18nQuery::create()
                ->filterById($descendant->getId())
                ->findByLocale('en_US');

            $newArray['title'] = $content->getColumnValues('title')[0] . ' (id: ' . $descendant->getId() . ')';
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }

    /**
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

    /**
     * @throws PropelException
     */
    public function loadTableBrowserLang(CustomFrontMenuItem $parent, string $lang) : array
    {
        $dataArray = [];
        $descendants = $parent->getChildren();
        foreach ($descendants as $descendant) {
            $newArray = [];
            $I18nMenus = CustomFrontMenuItemI18nQuery::create()->findById($descendant->getId());

            if (count($I18nMenus) <= 0){
                throw new PropelException('No content found for the given id:' . $descendant->getId());
            }

            $found = false;
            $title = '';
            $url = '';
            foreach ($I18nMenus as $I18nMenu) {
                if ($I18nMenu->getLocale() === $lang) {
                    $title = $I18nMenu->getTitle();
                    $url = $I18nMenu->getUrl();
                    $found = true;
                    break;
                }
                elseif ($I18nMenu->getLocale() === 'en_US') {
                    $title = $I18nMenu->getTitle();
                    $url = $I18nMenu->getUrl();
                }
            }

            if (!$found) {
                $title = $I18nMenus->getColumnValues('title')[0];
                $url = $I18nMenus->getColumnValues('url')[0];
            }

            $newArray['title'] = $title;
            $newArray['url'] = $url;

            $newArray['depth'] = $descendant->getLevel() - 2;
            $newArray['id'] = $this
                ->COUNT_ID;
            ++$this->COUNT_ID;

            if ($descendant->hasChildren()) {
                $newArray['children'] = $this->loadTableBrowserLang($descendant, $lang);
            }
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }
}