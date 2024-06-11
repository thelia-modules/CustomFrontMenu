<?php

namespace CustomFrontMenu\Service;

use Exception;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Validator
{
    function __construct(
        protected SessionInterface $session
    )
    {

    }
    /**
     * Manage the problems with empty fields or back quotes presence
     */
    public static function stringValidation(string $string, SessionInterface $session) : string
    {
        $string = trim($string);
        if (strlen($string) === 0) {
            $string = "Empty field";
            $session->getFlashBag()->add('warning', 'One or more empty fields were replaced by the tag "Empty field".');
        }
        return self::backQuoteProhibited($string, $session);
    }

    /**
     * Replace back quotes with simple quotes and add a warning flash message.
     */
    public static function backQuoteProhibited(string $string, SessionInterface $session) : string
    {
        $string = trim($string);
        if (str_contains($string, '`')) {
            $string = str_replace('`', "'", $string);
            $session->getFlashBag()->add('warning', "One or more back quotes were replaced by simple quotes : ` -> ' .");
        }
        return $string;
    }

    public static function htmlSafeValidation(string $string, SessionInterface $session, bool $canBeEmpty = false) : string
    {
        $string = trim($string);

        $string = strip_tags($string);

        if ($canBeEmpty) {
            $string = self::stringValidation($string, $session);
        }

        //$string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        return $string;
    }

    public static function sqlSafeValidation(string $string, SessionInterface $session, bool $canBeEmpty = false) : string
    {
        $string = trim($string);

        if ($canBeEmpty) {
            $string = self::stringValidation($string, $session);
        }

        return addslashes($string);
    }

    public static function filterValidation(string $string, int $filter): string
    {
        if (filter_var($string, $filter)) {
            return $string;
        }
        return '';
    }

    /**
     * Check the empty space, back quote, html and sql constraints
     */
    public static function completeValidation(string $string, SessionInterface $session) : string
    {
        $string = self::stringValidation($string, $session);
        $string = self::htmlSafeValidation($string, $session, true);
        return self::sqlSafeValidation($string, $session, true);
    }

    /**
     * /**
     *  Check if the string is a valid view for the url generation.
     *
     *  Valid strings : 'brand', 'category', 'content', 'folder', 'product'.
     *  (case-insensitive)
     * @param string $string
     * @param bool $stringReturn True : return the valid string | False : return a boolean
     * @return string|bool Return the valid string if $stringReturn is true, else a boolean
     * @throws Exception
     */
    public static function viewIsValid(string $string, bool $stringReturn) : string|bool
    {
        $type = strtolower($string);
        $validTypes = ['brand', 'category', 'content', 'folder', 'product'];
        if (!in_array($type, $validTypes)) {
            if (!$stringReturn) {
                return false;
            }
            throw new Exception('Invalid view type : '.$string);
        }
        if (!$stringReturn) {
            return true;
        }
        return $string;
    }

    /**
     * @throws Exception
     */
    public static function viewIsValidOrEmpty(string $string) : string
    {
        $type = strtolower($string);
        if (self::viewIsValid($type, false) || $type === 'empty') {
            return $string;
        }
        throw new Exception('Invalid view type : '.$string);
    }
}

