<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;

interface CFMLoadInterface
{
    /**
     * Load the different menu names
     * @param CustomFrontMenuItem $root The menu root
     * @return array All the menu names
     */
    public function loadSelectMenu(CustomFrontMenuItem $root) : array;

    /**
     * Load all elements from the database recursively to parse them in an array
     * @param CustomFrontMenuItem $parent
     * @return array All the descendants items of the menu root given in parameter
     */
    public function loadTableBrowser(CustomFrontMenuItem $parent) : array;

    /**
     * Load all elements from the database recursively to parse them in an array with a lang
     * @param CustomFrontMenuItem $parent
     * @param string $lang
     * @return array All the descendants items of the menu root given in parameter
     */
    public function loadTableBrowserLang(CustomFrontMenuItem $parent, string $lang) : array;
}