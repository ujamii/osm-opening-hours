<?php

namespace Ujamii\OsmOpeningHours;

use Spatie\OpeningHours\OpeningHours;
use Ujamii\OsmOpeningHours\Filters\Filter;

class OsmStringToOpeningHoursConverter
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

    public static function openingHoursFromOsmString(string $osmOpeningHours, array $specialFilters = []): OpeningHours
    {
        $configArray = self::configArrayFromOsmString($osmOpeningHours, $specialFilters);

        return OpeningHours::create($configArray);
    }

    /**
     * @param string $osmOpeningHours
     * @param array $specialFilters
     *
     * @return array
     * @see https://wiki.openstreetmap.org/wiki/Key:opening_hours#Summary_syntax
     */
    public static function configArrayFromOsmString(string $osmOpeningHours, array $specialFilters = []): array
    {
        $resultingConfig = [];
        $ruleSets = explode(';', $osmOpeningHours);
        foreach ($ruleSets as $ruleSet) {
            $parsedRuleset = self::parseRuleSet($ruleSet, $specialFilters);
            $exceptions = [];
            if (isset($parsedRuleset['exceptions'])) {
                $exceptions = $parsedRuleset['exceptions'];
                unset($parsedRuleset['exceptions']);
            }
            // this must not be merged by default as later rules overwrite earlier ones
            // on the other hand it HAS merged to be when certain criteria are connected (like "odd weeks only")
            foreach ($parsedRuleset as $dayKey => $parsedDayItem) {
                $mergedConfigs = $parsedDayItem;
                if (isset($resultingConfig[$dayKey])) {
                    foreach ($resultingConfig[$dayKey] as $oldData) {
                        if (is_array($oldData)) {
                            array_unshift($mergedConfigs, $oldData);
                        }
                    }
                }
                $resultingConfig[$dayKey] = $mergedConfigs;
            }

            if (!empty($exceptions)) {
                $resultingConfig['exceptions'] = array_merge_recursive($resultingConfig['exceptions'] ?? [], $exceptions);
            }
        }

        return $resultingConfig;
    }

    protected static function parseRuleSet(string $ruleSet, array $specialFilters = []): array
    {
        $ruleSet = trim($ruleSet);
        if ('' === $ruleSet) {
            return [];
        }

        /**
         * @var string $filterName
         * @var Filter $callableFilter
         */
        foreach ($specialFilters as $filterName => $callableFilter) {
            if (!$callableFilter instanceof Filter) {
                throw new \RuntimeException(sprintf(
                    'Filter %s for ruleset %s needs to implement the Filter interface.',
                    get_class($callableFilter),
                    $ruleSet,
                ));
            }

            if (str_starts_with($ruleSet, $filterName)) {
                if (str_ends_with($ruleSet, 'off')) {
                    $hours = [];
                } else {
                    preg_match('%((?:\d\d:\d\d-\d\d:\d\d,?)+)%', $ruleSet, $matches);
                    $hours = explode(',', $matches[1]);
                }

                $callableFilter->setOpeningHours($hours);

                return ['filters' => [[$callableFilter, 'applyFilter']]];
            }
        }

        if ('24/7' === $ruleSet) {
            return self::getWeekdayArray('Mo-Su', '00:00-24:00');
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
        //var_dump($ruleSet);
        preg_match('%(week [0-9/-]+ )?((?:(Mo|Tu|We|Th|Fr|Sa|Su)[-,]?)+) ?((?:\d\d:\d\d-\d\d:\d\d,?)+)%', $ruleSet, $matches);
        //var_dump($matches);
        [$fullMatch, $weeks, $weekdayRangeFull, $weekdayEnd, $openingHours] = $matches;

        return self::getWeekdayArray($weekdayRangeFull, $openingHours, $weeks);
    }

    protected static function getWeekdayArray(string $weekdayRangeFull, string $openingHours, string $weeks = ''): array
    {
        $weekDayNamesShort = array_keys(self::WEEKDAYS);
        $weekInfo = self::parseWeeks($weeks);
        $isRange = strstr($weekdayRangeFull, '-');
        $isList = strstr($weekdayRangeFull, ',');

        $hoursValue = explode(',', $openingHours);

        if (null !== $weekInfo) {
            foreach ($hoursValue as $key => $value) {
                $hoursValue[$key] = [$value, 'data' => $weekInfo];
            }
        }
        $weekdays = [];
        foreach (explode(',', $weekdayRangeFull) as $match) {
            if (str_contains($match, '-')) {
                [$weekdayStart, $weekdayEnd] = explode('-', $match);
                $startIndex = array_search($weekdayStart, $weekDayNamesShort, true);
                $endIndex = !empty($weekdayEnd) ? array_search($weekdayEnd, $weekDayNamesShort, true) : $startIndex;

                for ($i = $startIndex; $i <= $endIndex; $i++) {
                    $weekdays[self::WEEKDAYS[$weekDayNamesShort[$i]]] = $hoursValue;
                }
            } else {
                $listOfDays = explode(',', $match);
                foreach ($listOfDays as $day) {
                    $weekdays[self::WEEKDAYS[$day]] = $hoursValue;
                }
            }
        }

        return $weekdays;
    }

    protected static function parseException(string $ruleSet): array
    {
        preg_match('%(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)? ?(\d\d)(-(\d\d))?%', $ruleSet, $matches);

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
        if (preg_match('%week (\d\d)-(\d\d)/2%', $input, $matches)) {
            if ((int) $matches[1] % 2 === 1) {
                return self::WEEKS_ODD;
            }
            if ((int) $matches[1] % 2 === 0) {
                return self::WEEKS_EVEN;
            }
        }

        return null;
    }
}
