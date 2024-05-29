<?php

namespace CustomFrontMenu\Service;

use CustomFrontMenu\Service\Validator;
use Exception;
use CustomFrontMenu\Interface\CFMSaveInterface;
use CustomFrontMenu\Model\CustomFrontMenuItemI18n;
use Propel\Runtime\Exception\PropelException;
use CustomFrontMenu\Model\CustomFrontMenuItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CFMSaveService implements CFMSaveInterface
{
    protected $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Save all elements from an array recursively to the database
     * @throws PropelException
     * @throws Exception
     */

    public function saveTableBrowser(array $dataArray, CustomFrontMenuItem $parent, string $locale) : void
    {
        foreach ($dataArray as $element) {
            $item = new CustomFrontMenuItem();
            $item->insertAsLastChildOf($parent);

            $item->save();

            $content = new CustomFrontMenuItemI18n();
            $content->setTitle(Validator::completeValidation($element['title'], $session));
            $content->setUrl(Validator::filterValidation(Validator::htmlSafeValidation($element['url'], $session), FilterType::URL));
            $content->setId($item->getId());
            $content->setLocale($locale);
            $content->save();

            if (isset($element['children']) && $element['children'] !== []) {
                $this->saveTableBrowser($element['children'], $item, $locale);
            }
            $parent->save();
        }
    }

}