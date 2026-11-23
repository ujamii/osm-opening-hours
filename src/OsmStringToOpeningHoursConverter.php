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

    public static function openingHoursFromOsmString(string $osmOpeningHours, array $specialFilters = []): OpeningHours
    {
        $configArray = self::configArrayFromOsmString($osmOpeningHours, $specialFilters);

        return OpeningHours::create($configArray);
    }

    /**
     * Converts the OSM string leniently: rules that cannot be parsed are skipped. Use validate() to detect them.
     *
     * Later rules override earlier ones. Plain weekday rules and recurring dates become weekday and exception
     * entries; everything else becomes a filter. As spatie/opening-hours checks filters before exceptions and
     * exceptions before weekdays, a rule that may overlap with an entry checked earlier becomes a filter as well.
     * Special filters (like public holidays) always take precedence over the other rules.
     *
     * @see https://wiki.openstreetmap.org/wiki/Key:opening_hours#Summary_syntax
     */
    public static function configArrayFromOsmString(string $osmOpeningHours, array $specialFilters = []): array
    {
        self::assertFilters($specialFilters);

        $config = [];
        $specialFilterCallbacks = [];
        $ruleFilters = [];
        $filterWeekdays = [];
        foreach (self::splitRules($osmOpeningHours) as $ruleString) {
            try {
                $specialFilter = self::applySpecialFilter($ruleString, $specialFilters);
                if (null !== $specialFilter) {
                    array_unshift($specialFilterCallbacks, [$specialFilter, 'applyFilter']);

                    continue;
                }
                $rule = Rule::fromString($ruleString);
            } catch (InvalidOsmStringException) {
                continue;
            }

            if (
                self::addWeekdayRule($config, $rule, $filterWeekdays)
                || ([] === $ruleFilters && self::addExceptionRule($config, $rule))
            ) {
                continue;
            }
            array_unshift($ruleFilters, $rule);
            $filterWeekdays = [...$filterWeekdays, ...(self::plainWeekdays($rule) ?? range(1, 7))];
        }

        $filters = [...$specialFilterCallbacks, ...$ruleFilters];
        if ([] !== $filters) {
            $config['filters'] = $filters;
        }

        return $config;
    }

    /**
     * @throws InvalidOsmStringException
     */
    public static function validate(string $osmOpeningHours, array $specialFilters = []): void
    {
        self::assertFilters($specialFilters);

        foreach (self::splitRules($osmOpeningHours) as $ruleString) {
            self::applySpecialFilter($ruleString, $specialFilters) ?? Rule::fromString($ruleString);
        }
    }

    public static function isValid(string $osmOpeningHours, array $specialFilters = []): bool
    {
        try {
            self::validate($osmOpeningHours, $specialFilters);
        } catch (InvalidOsmStringException) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string>
     */
    private static function splitRules(string $osmOpeningHours): array
    {
        return array_filter(array_map(trim(...), explode(';', $osmOpeningHours)));
    }

    private static function assertFilters(array $specialFilters): void
    {
        foreach ($specialFilters as $filterName => $filter) {
            if (!$filter instanceof Filter) {
                throw new \RuntimeException(sprintf(
                    'Filter %s for %s needs to implement the Filter interface.',
                    get_debug_type($filter),
                    $filterName,
                ));
            }
        }
    }

    /**
     * Passes the hours of a rule like `PH 09:00-12:00` or `PH off` to the matching filter.
     *
     * @param array<string, Filter> $specialFilters
     */
    private static function applySpecialFilter(string $ruleString, array $specialFilters): ?Filter
    {
        foreach ($specialFilters as $filterName => $filter) {
            if (!str_starts_with($ruleString, $filterName)) {
                continue;
            }

            $rule = Rule::fromString(substr($ruleString, strlen($filterName)));
            if ([] !== $rule->selectors()) {
                throw new InvalidOsmStringException(sprintf('Rule "%s" only supports hours.', $ruleString));
            }

            return $filter->setOpeningHours($rule->hours);
        }

        return null;
    }

    /**
     * Sets the hours of plain weekday rules, unless one of their days is covered by an earlier filter.
     *
     * @param list<int> $filterWeekdays
     */
    private static function addWeekdayRule(array &$config, Rule $rule, array $filterWeekdays): bool
    {
        $weekdays = self::plainWeekdays($rule);
        if (
            null === $weekdays
            || isset($config['exceptions'])
            || null !== $rule->years
            || null !== $rule->monthdays
            || null !== $rule->weeks
            || [] !== array_intersect($weekdays, $filterWeekdays)
        ) {
            return false;
        }

        $weekdayNames = array_values(self::WEEKDAYS);
        foreach ($weekdays as $weekday) {
            $dayKey = $weekdayNames[$weekday - 1];
            if ([] === $rule->hours) {
                unset($config[$dayKey]);

                continue;
            }

            $config[$dayKey] = $rule->hours;
        }

        return true;
    }

    /**
     * Sets the hours of rules that consist of recurring dates only, like `Dec 24-26 off`.
     */
    private static function addExceptionRule(array &$config, Rule $rule): bool
    {
        $dayKeys = $rule->monthdays?->dayKeys();
        if (null === $dayKeys || null !== $rule->years || null !== $rule->weeks || null !== $rule->weekdays) {
            return false;
        }

        $config['exceptions'] = array_replace($config['exceptions'] ?? [], array_fill_keys($dayKeys, $rule->hours));

        return true;
    }

    /**
     * Returns the ISO weekdays a rule is limited to, or null if it may apply to any weekday.
     *
     * @return list<int>|null
     */
    private static function plainWeekdays(Rule $rule): ?array
    {
        if (null === $rule->weekdays) {
            return range(1, 7);
        }

        return $rule->weekdays->isPlain() ? $rule->weekdays->days : null;
    }
}
