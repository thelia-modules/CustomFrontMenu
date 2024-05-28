<?php

namespace CustomFrontMenu\Interface;

use CustomFrontMenu\Model\CustomFrontMenuItem;

interface CFMSaveInterface
{
    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent, string $locale): void;
}