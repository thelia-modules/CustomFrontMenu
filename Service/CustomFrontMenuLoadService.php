<?php

declare(strict_types=1);

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Exception;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
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
     * Public URL of a menu target, in the given locale.
     *
     * getUrl() goes through URL::retrieve(), which returns the rewritten URL when the
     * target has one and falls back to the ?view=&{view}_id= form when it has not, so
     * the menu links match what the rest of the front links to.
     *
     * @throws PropelException
     */
    public function resolvePublicUrl(string $view, int $viewId, string $locale): ?string
    {
        $queryClass = match (strtolower($view)) {
            'brand' => BrandQuery::class,
            'category' => CategoryQuery::class,
            'content' => ContentQuery::class,
            'folder' => FolderQuery::class,
            'product' => ProductQuery::class,
            default => null,
        };

        if (null === $queryClass) {
            return null;
        }

        $target = $queryClass::create()->findPk($viewId);

        // Target deleted since the menu was composed: no link rather than a broken one.
        return $target?->getUrl($locale);
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

            if($view && $viewId && Validator::viewIsValid($view)) {

                $formatedView = ucfirst($view);
                $class = 'Thelia\Model\\' . $formatedView . 'Query';
                if (!class_exists($class)) {
                    throw new Exception("Class $class does not exist.");
                }
                /** @var CategoryQuery|ProductQuery|FolderQuery|ContentQuery|BrandQuery $objectQuery */
                $objectQuery = $class::create();

                $query = $objectQuery
                    ->filterById($viewId)
                    ->joinWith($formatedView.'I18n')
                    ->find();

                $queryI18n = $query->getColumnValues($formatedView.'I18ns')[0];

                if ($query->isEmpty()) {
                    throw new Exception("No results found for the specified id $viewId.");
                }

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

    /**
     * Load all elements from the database recursively to parse them in an array with a lang
     * @param CustomFrontMenuItem $parent
     * @param string $lang
     * @return array All the descendants items of the menu root given in parameter
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

            if (Validator::viewIsValid($descendant->getView())) {
                $view = $descendant->getView();
                $viewId = $descendant->getViewId();
                if ($view && $viewId) {
                    $newArray['url'] = $this->resolvePublicUrl($view, (int) $viewId, $lang) ?? '';
                }
            }

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