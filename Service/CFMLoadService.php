<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Interface\CFMLoadInterface;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;

class CFMLoadService implements CFMLoadInterface
{
    public function __construct(private int $COUNT_ID = 1)
    {}

    /**
     * Load all elements from the database recursively to parse them in an array
     * @throws PropelException
     */
    public function loadTableBrowser(CustomFrontMenuItem $parent, string $locale) : array
    {
        $dataArray = [];
        $descendants = $parent->getChildren();
        foreach ($descendants as $descendant) {
            $newArray = [];
            $content = CustomFrontMenuItemI18nQuery::create()
                ->filterById($descendant->getId()); // ->findByLocale($locale)
            
            $results = $content->find()->toArray();
            if (count($results) <= 0){
                throw new PropelException('No content found for the given id');
            }

            $found = false;
            foreach ($results as $result) {
                if ($result['Locale'] === $locale) {
                    $found = true;
                    $newArray["title"] = $result['Title'];
                    $newArray["url"] = $result['Url'];
                    break;
                }
                else if ($result['Locale'] === 'en_US') {
                    $found = true;
                    $newArray["title"] = $result['Title'];
                    $newArray["url"] = $result['Url'];
                }
            }

            if (!$found) {
                $newArray["title"] = $results[0]['Title'];
                $newArray["url"] = $results[0]['Url'];
            }

            $newArray['depth'] = $descendant->getLevel() - 2;
            $newArray['id'] = $this->COUNT_ID;
            ++$this->COUNT_ID;

            if ($descendant->hasChildren()) {
                $newArray['children'] = $this->loadTableBrowser($descendant, $locale);
            }
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }
}