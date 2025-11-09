<?php

namespace Ad2210\MultiProjectTeamPlanning\Service;

use DateTimeInterface;
use IntlDateFormatter;

final class DateFormatter
{

    public static function formatIcu(DateTimeInterface $dt, string $icuPattern, string $locale = 'fr_FR', string $timezone = 'Europe/Paris'): string
    {
        if (class_exists(IntlDateFormatter::class)) {
            $fmt = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                $timezone,
                null,
                $icuPattern
            );
            $res = $fmt->format($dt);
            if ($res !== false) {
                return $res;
            }
        }
        // Fallback PHP
        return $dt->format(self::icuToPhp($icuPattern));
    }

    /** Conversion simple ICU -> PHP */
    public static function icuToPhp(string $p): string
    {
        // Remplacements ordonnés du plus long au plus court
        $map = [
            // Jour semaine
            'EEEE' => 'l', // Monday
            'EEE'  => 'D', // Mon
            // Mois
            'MMMM' => 'F', // November
            'MMM'  => 'M', // Nov
            'MM'   => 'm', // 11
            // Jour
            'dd'   => 'd',
            // Année
            'yyyy' => 'Y',
            'yy'   => 'y',
            // Heure / minute
            'HH'   => 'H', // 00-23
            'H'    => 'G', // 0-23
            'mm'   => 'i', // minutes
        ];
        return strtr($p, $map);
    }
}