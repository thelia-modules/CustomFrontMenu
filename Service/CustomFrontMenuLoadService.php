<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Exception;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Base\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;

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
     * Generate an url basis on a view type and an id to get the associated content page.
     */
    public function generateUrl(string $type, int $id, string $lang = null): string
    {
        // url of type http://cfm.th/?view=product&product_id=21&lang=en_US

        $parameters = ['view' => strtolower($type), strtolower($type).'_id' => $id];
        if($lang) {
            $parameters['lang'] = $lang;
        }
        return URL::getInstance()->absoluteUrl('', $parameters);
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

            foreach ($I18nMenus as $I18nMenu) {
                $newArray['title'][$I18nMenu->getLocale()] = $I18nMenu->getTitle();
                $newArray['url'][$I18nMenu->getLocale()] = $I18nMenu->getUrl();
            }

            $view = $descendant->getView();
            if (!$view){
                $view = 'url';
            }
            $newArray['type'] = $view;
            $viewId = $descendant->getViewId();

            if($view && $viewId && Validator::viewIsValid($view, false)) {

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
                $newArray['url']['en_US'] = $title.'-'.$viewId;
                if (strtolower($view) === 'product') {
                    $newArray['url']['en_US'] = $title.'-'.$query->getFirst()->getRef().'-'.$viewId; ;
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

            if (!isset($url) && Validator::viewIsValid($descendant->getView(), false)) {
                $view = $descendant->getView();
                $viewId = $descendant->getViewId();
                if (isset($view) && isset($viewId)) {
                    $newArray['url'] = $this->generateUrl($view, $viewId, $lang);
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