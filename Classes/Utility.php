<?php

namespace DKM\FluxMigrate;

class Utility
{
    /**
     * @param string $value
     * @param string $delimiters
     * @param bool $lowerCamelCase
     * @return string
     */
    public static function camelCase(string $value, string $delimiters = '.', bool $lowerCamelCase = false): string
    {
        $value =  str_replace(str_split($delimiters), "", ucwords($value, $delimiters));
        return $lowerCamelCase ? lcfirst($value) : ucfirst($value);
    }
}