<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;

interface CFMLoadInterface
{
    public function loadSelectMenu(CustomFrontMenuItem $root) : array;

    public function loadTableBrowser(CustomFrontMenuItem $parent) : array;
}