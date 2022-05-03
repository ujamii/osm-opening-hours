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

    public const MONTHS = [
        'Jan' => '01',
        'Feb' => '02',
        'Mar' => '03',
        'Apr' => '04',
        'May' => '05',
        'Jun' => '06',
        'Jul' => '07',
        'Aug' => '08',
        'Sep' => '09',
        'Oct' => '10',
        'Nov' => '11',
        'Dec' => '12',
    ];

    public const WEEKS_ODD = 1;
    public const WEEKS_EVEN = 2;

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
            $parsedRuleset = self::parseRuleSet($ruleSet);
            $exceptions = [];
            if (isset($parsedRuleset['exceptions'])) {
                $exceptions = $parsedRuleset['exceptions'];
                unset($parsedRuleset['exceptions']);
            }
            $resultingConfig = array_merge($resultingConfig, $parsedRuleset);
            if (!empty($exceptions)) {
                $resultingConfig['exceptions'] = array_merge_recursive($resultingConfig['exceptions'] ?? [], $exceptions);
            }
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

        if (str_ends_with($ruleSet, 'off')) {
            return ['exceptions' => self::parseException($ruleSet)];
        }

        // TODO: years
        // TODO: year_range
        // TODO: year
        // TODO: months
        // TODO: monthdays
        // TODO: week_range
        // TODO: holidays
//var_dump($ruleSet);
        preg_match('%(week [0-9/-]+ )?((Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))? )?((?:\d\d:\d\d-\d\d:\d\d,?)+)%', $ruleSet, $matches);
//var_dump($matches);
        [$fullMatch, $weeks, $weekdayRangeFull, $weekdayStart, $weekdayRangeEnd, $weekdayEnd, $openingHours] = $matches;

        return self::getWeekdayArray($weekdayStart, $weekdayEnd, $openingHours, $weeks);
    }

    protected static function getWeekdayArray(string $weekdayStart, string $weekdayEnd, string $openingHours, string $weeks = ''): array
    {
        $weekDayNamesShort = array_keys(self::WEEKDAYS);
        $startIndex = array_search($weekdayStart, $weekDayNamesShort, true);
        $endIndex = !empty($weekdayEnd) ? array_search($weekdayEnd, $weekDayNamesShort, true) : $startIndex;

        $weekInfo = self::parseWeeks($weeks);

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $hoursValue = explode(',', $openingHours);

            if (null !== $weekInfo) {
                $hoursValue['data'] = $weekInfo;
            }
            $weekdays[self::WEEKDAYS[$weekDayNamesShort[$i]]] = $hoursValue;
        }

        return $weekdays ?? [];
    }

    protected static function parseException(string $ruleSet): array
    {
        \Safe\preg_match('%(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)? (\d\d)(-(\d\d))?%', $ruleSet, $matches);

        if (0 === count($matches)) {
            return [];
        }

        $monthName = $matches[1];
        $dayStart = $matches[2];
        $dayEnd = $matches[4] ?? '';
        return self::getDaysArray($monthName, $dayStart, $dayEnd);
    }

    protected static function getDaysArray(string $monthName, string $dayStart, string $dayEnd, string $openingHours = ''): array
    {
        $daysArray = [];
        $startDay = (int) $dayStart;
        $endDay = empty($dayEnd) ? $dayStart : (int) $dayEnd;
        for ($i = $startDay; $i <= $endDay; $i++) {
            $hoursValue = $openingHours ? [$openingHours] : [];
            $daysArray[self::MONTHS[$monthName] . '-' . sprintf('%02d', $i)] = $hoursValue;
        }
        return $daysArray;
    }

    protected static function parseWeeks(string $input): ?int
    {
        $input = trim($input);
        if ('' === $input) {
            return null;
        }
        // TODO: this may also be 01-51/3, so be more flexible here
        if (\Safe\preg_match('%week (\d\d)-(\d\d)/2%', $input, $matches)) {
            if ((int) $matches[1] % 2 === 1) {
                return self::WEEKS_ODD;
            }
        }
        if ((int) $matches[1] % 2 === 0) {
            return self::WEEKS_EVEN;
        }

        return null;
    }
}
