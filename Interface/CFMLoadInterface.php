<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;

interface CFMLoadInterface
{
    public function loadSelectMenu($root, string $locale) : array;

    public function loadTableBrowser(CustomFrontMenuItem $parent) : array;
}