<?php

namespace CustomFrontMenu\Service;

class Validator
{
    public static function stringValidation(string $string) : string
    {
        $string = trim($string);
        if (strlen($string) === 0) {
            $string = "Empty field";
        }
        return $string;
    }

    public static function htmlSafeValidation(string $string, bool $canBeEmpty = false) : string
    {
        $string = trim($string);

        $string = strip_tags($string);

        if ($canBeEmpty) {
            $string = self::stringValidation($string);
        }

        //$string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        return $string;
    }

    public static function sqlSafeValidation(string $string, bool $canBeEmpty = false) : string
    {
        $string = trim($string);

        if ($canBeEmpty) {
            $string = self::stringValidation($string);
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
     * @throws \Exception
     */
    public static function completeValidation(string $string) : string
    {
        $string = self::stringValidation($string);
        $string = self::htmlSafeValidation($string, true);
        return self::sqlSafeValidation($string, true);
    }
}

