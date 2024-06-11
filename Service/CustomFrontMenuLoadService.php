<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use CustomFrontMenu\Model\CustomFrontMenuItemI18nQuery;
use Exception;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class CustomFrontMenuLoadService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private int $COUNT_ID = 1
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
     */
    public function loadTableBrowser(CustomFrontMenuItem $parent) : array
    {
        $dataArray = [];

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        /** @var Session $session */
        $session = $request->getSession();
        $localeAdmin = $session->get('thelia.current.admin_lang')->getLocale();

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
            
            if (!$view || $view === ""){
                $view = 'url';
            }
            $newArray['type'] = $view;
            $viewId = $descendant->getViewId();

            if($view && $viewId && Validator::viewIsValid($view, false)) {
                $con = Propel::getConnection();

                $table = strtolower($newArray['type']).'_i18n';
                $stmt = $con->prepare("SELECT locale, title FROM $table WHERE id = :id;");
                $stmt->bindValue(':id', $viewId, \PDO::PARAM_INT);
                $stmt->execute();

                $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $found = false;
                foreach ($results as $arrayContent) {
                    if($arrayContent['locale'] === $localeAdmin) {
                        $newArray['url']['en_US'] = $arrayContent['title'].'-'.$viewId;
                        $found = true;
                    }
                }
                if(!$found) {
                    $newArray['url']['en_US'] = "";
                    if ($results[0] && $results[0]['title']) {
                        $newArray['url']['en_US'] = $results[0]['title'].'-'.$viewId;
                    }
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