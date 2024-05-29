<?php

namespace CustomFrontMenu\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Validator
{
    public static function stringValidation(string $string, SessionInterface $session) : string
    {
        $string = trim($string);
        if (strlen($string) === 0) {
            $string = "Empty field";
            $session->getFlashBag()->add('fail', 'One or more empty fields were replaced by the tag "Empty field".');
        }
        return self::backQuoteProhibed($string, $session);
    }

    public static function backQuoteProhibed(string $string, SessionInterface $session) : string
    {
        $string = trim($string);
        if (strpos($string, '`') !== false) {
            $string = str_replace('`', "'", $string);
            $session->getFlashBag()->add('fail', "One or more back quotes were replaced by simple quotes : ` -> ' .");
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

    public static function completeValidation(string $string, SessionInterface $session) : string
    {
        $string = self::stringValidation($string, $session);
        $string = self::htmlSafeValidation($string, $session, true);
        return self::sqlSafeValidation($string, $session, true);
    }
}

