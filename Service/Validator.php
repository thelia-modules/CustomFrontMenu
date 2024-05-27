<?php

namespace CustomFrontMenu\Service;

class Validator
{
    /**
     * @throws \Exception
     */
    public static function stringValidation(string $string) : string
    {
        $string = trim($string);
        if (strlen($string) === 0) {
            $string = "Empty field";
        }
        return $string;
    }

    public static function htmlSafeValidation(string $string) : string
    {
        $string = trim($string);

        $string = strip_tags($string);

        //$string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        return $string;
    }

    /**
     * @throws \Exception
     */
    public static function completeValidation(string $string) : string
    {
        return self::stringValidation(self::htmlSafeValidation(self::stringValidation($string)));
    }
}