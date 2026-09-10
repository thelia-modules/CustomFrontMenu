<?php

declare(strict_types=1);

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;

class CustomFrontMenuLoadService
{
    public function __construct(
        protected readonly RequestStack $requestStack,
        protected int $COUNT_ID = 1
    )
    {}

    /**
     * Load the different menu names
     * @param CustomFrontMenuItem $root The menu root
     * @return array All the menu names
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
                ->findOneByLocale('en_US');

            $newArray['title'] = $content->getTitle() . ' (id: ' . $descendant->getId() . ')';
            $dataArray[] = $newArray;
        }
        return $dataArray;
    }

    /**
     * Load all elements from the database recursively to parse them in an array
     * @param CustomFrontMenuItem $parent
     * @return array All the descendants items of the menu root given in parameter
     * @throws PropelException
     * @throws Exception
     */
    public function loadTableBrowser(CustomFrontMenuItem $parent) : array
    {
        $dataArray = [];

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession();

        $descendants = $parent->getChildren();
        foreach ($descendants as $descendant) {
            $newArray = [];
            $I18nMenus = CustomFrontMenuItemI18nQuery::create()
                ->findById($descendant->getId());

            if (count($I18nMenus) <= 0){
                throw new PropelException('No content found for the given id:' . $descendant->getId());
            }

            $view = $descendant->getView();
            if (!$view){
                $view = 'url';
            }
            $newArray['type'] = $view;
            foreach ($I18nMenus as $I18nMenu) {
                $newArray['title'][$I18nMenu->getLocale()] = $I18nMenu->getTitle();
                if($view === 'url') {
                    $newArray['url'][$I18nMenu->getLocale()] = $I18nMenu->getUrl();
                }
            }

            
            $viewId = $descendant->getViewId();

            // $view is never empty here: it defaults to 'url' just above.
            if($viewId && Validator::viewIsValid($view)) {

                $formatedView = ucfirst($view);
                $class = 'Thelia\Model\\' . $formatedView . 'Query';
                if (!class_exists($class)) {
                    throw new Exception("Class $class does not exist.");
                }
                /** @var CategoryQuery|ProductQuery|FolderQuery|ContentQuery|BrandQuery $objectQuery */
                $objectQuery = $class::create();

                /** @var ObjectCollection $query */
                $query = $objectQuery
                    ->filterById($viewId)
                    ->joinWith($formatedView.'I18n')
                    ->find();

                // Emptiness first: reading the translations of a missing row raised an
                // index error before this check could report the real problem.
                if ($query->isEmpty()) {
                    throw new Exception("No results found for the specified id $viewId.");
                }

                $queryI18n = $query->getColumnValues($formatedView.'I18ns')[0];

                $title = null;
                foreach ($queryI18n as $item) {
                    if ($item->getLocale() === $session->getAdminLang()->getLocale()) {
                        $title = $item->getTitle();
                        break;
                    }
                    if ($item->getLocale() === 'en_US') {
                        $title = $item->getTitle();
                    }
                }
                if (!$title) {
                     $title = $queryI18n[0]->getTitle();
                }
                $newArray['typeId'] = $title.'-'.$viewId;
                if (strtolower($view) === 'product') {
                    $newArray['typeId'] = $title.'-'.$query->getFirst()->getRef().'-'.$viewId; ;
                }
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
