<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;

interface CFMLoadInterface
{
    public function loadTableBrowser(CustomFrontMenuItem $parent, string $locale) : array;
}