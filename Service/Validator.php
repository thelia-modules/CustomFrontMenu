<?php

namespace CustomFrontMenu\Service;

use ContainerKz7i3JQ\getCartAddService;

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

    public static function filterValidation(string $string, string $filter): string
    {
        $filterToUse = match ($filter) {
            'url' => FILTER_SANITIZE_URL,
            'email' => FILTER_SANITIZE_EMAIL,
            default => throw new \InvalidArgumentException('Invalid filter provided'),
        };
        if (filter_var($string, $filterToUse)) {
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