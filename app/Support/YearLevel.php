<?php

namespace App\Support;

class YearLevel
{
    public static function label(?int $yearLevel): string
    {
        return match ($yearLevel) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => 'Year Level Not Set',
        };
    }
}
