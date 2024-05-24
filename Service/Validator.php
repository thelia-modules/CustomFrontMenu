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
            throw new \Exception('Cannot use empty or full space string');
        }
        return $string;
    }

    public static function htmlSafeValidation(string $string) : string
    {
        $string = trim($string);

        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @throws \Exception
     */
    public static function completeValidation(string $string) : string
    {
        return self::htmlSafeValidation(self::stringValidation($string));
    }
}