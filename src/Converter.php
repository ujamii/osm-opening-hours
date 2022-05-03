<?php

namespace Ujamii\OsmOpeningHours;

use Spatie\OpeningHours\OpeningHours;

class Converter
{
    public const WEEKDAYS = [
        'Mo' => 'monday',
        'Tu' => 'tuesday',
        'We' => 'wednesday',
        'Th' => 'thursday',
        'Fr' => 'friday',
        'Sa' => 'saturday',
        'Su' => 'sunday',
    ];

    public static function openingHoursFromOsmString(string $osmOpeningHours): OpeningHours
    {
        $configArray = self::configArrayFromOsmString($osmOpeningHours);
        return OpeningHours::create($configArray);
    }

    /**
     * @param string $osmOpeningHours
     *
     * @return array
     * @see https://wiki.openstreetmap.org/wiki/Key:opening_hours#Summary_syntax
     */
    public static function configArrayFromOsmString(string $osmOpeningHours): array
    {
        $resultingConfig = [];
        $ruleSets = explode(';', $osmOpeningHours);
        foreach ($ruleSets as $ruleSet) {
            $resultingConfig = array_merge($resultingConfig, self::parseRuleSet($ruleSet));
        }
        return $resultingConfig;
    }

    protected static function parseRuleSet(string $ruleSet): array
    {
        $ruleSet = trim($ruleSet);
        if ('' === $ruleSet) {
            return [];
        }

        if ('24/7' === $ruleSet) {
            return self::getWeekdayArray('Mo', 'Su', '00:00-24:00');
        }

        // TODO: years
        // TODO: year_range
        // TODO: year
        // TODO: months
        // TODO: monthdays
        // TODO: week_range
        // TODO: holidays
//var_dump($ruleSet);
        preg_match('%(week [0-9/-]+ )?((Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))? )?(\d\d:\d\d-\d\d:\d\d)?%', $ruleSet, $matches);
//var_dump($matches);
        [$fullMatch, $weeks, $weekdayRangeFull, $weekdayStart, $weekdayRangeEnd, $weekdayEnd, $openingHours] = $matches;

        return self::getWeekdayArray($weekdayStart, $weekdayEnd, $openingHours);
    }

    protected static function getWeekdayArray(string $weekdayStart, string $weekdayEnd, string $openingHours): array
    {
        $weekDayNamesShort = array_keys(self::WEEKDAYS);
        $startIndex = array_search($weekdayStart, $weekDayNamesShort);
        $endIndex = !empty($weekdayEnd) ? array_search($weekdayEnd, $weekDayNamesShort) : $startIndex;

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $weekdays[self::WEEKDAYS[$weekDayNamesShort[$i]]] = [$openingHours];
        }

        return $weekdays;
    }

}