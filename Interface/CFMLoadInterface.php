<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

interface CFMLoadInterface
{
    /**
     * Load the different menu names
     * @param CustomFrontMenuItem $root The menu root
     * @return array All the menu names
     */
    public function loadSelectMenu(CustomFrontMenuItem $root) : array;

    /**
     * Generate an url basis on a view type and an id to get the associated content page.
     */
    public function generateUrl(string $type, int $id, string $lang = null): string;

    /**
     * Load all elements from the database recursively to parse them in an array
     * @param CustomFrontMenuItem $parent
     * @param SessionInterface $session
     * @param int $nbInvalidUrl Count the invalid url number (useful to display it in a warning for the admin panel in another function)
     * @return array All the descendants items of the menu root given in parameter
     */
    public function loadTableBrowser(CustomFrontMenuItem $parent, SessionInterface $session, int & $nbInvalidUrl) : array;

    /**
     * Load all elements from the database recursively to parse them in an array with a lang
     * @param CustomFrontMenuItem $parent
     * @param string $lang
     * @return array All the descendants items of the menu root given in parameter
     */
    public function loadTableBrowserLang(CustomFrontMenuItem $parent, string $lang) : array;
}