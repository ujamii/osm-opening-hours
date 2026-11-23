<?php

namespace Ujamii\OsmOpeningHours;

use Spatie\OpeningHours\Exceptions\Exception as OpeningHoursException;
use Spatie\OpeningHours\OpeningHoursForDay;
use Ujamii\OsmOpeningHours\Selectors\MonthdaySelector;
use Ujamii\OsmOpeningHours\Selectors\RangeSelector;
use Ujamii\OsmOpeningHours\Selectors\Selector;
use Ujamii\OsmOpeningHours\Selectors\WeekdaySelector;

/**
 * A single OSM rule like `2024 Dec 24-26: Mo-Fr 10:00-12:00` or `Sa[-1] +1 day off`.
 * Invoked with a date, it returns the opening hours of that date or null if the rule does not apply,
 * which makes it usable as a filter for spatie/opening-hours.
 *
 * @see https://wiki.openstreetmap.org/wiki/Key:opening_hours/specification
 */
final class Rule
{
    private const string SEPARATOR = '(?: |$)';
    private const string WIDE_RANGE_SEPARATOR = ':?' . self::SEPARATOR;
    private const string TIMES = '24/7|\d\d:\d\d-\d\d:\d\d(?:,\d\d:\d\d-\d\d:\d\d)*';

    /**
     * A leading year directly followed by a month belongs to the date range, like in `2026 Dec 24-2027 Jan 06`.
     */
    private const string PATTERN = '~^'
        . '(?:(?!\\d{4} ' . MonthdaySelector::MONTH . ')(?<years>' . RangeSelector::YEARS_PATTERN . ')' . self::WIDE_RANGE_SEPARATOR . ')?'
        . '(?:(?<monthdays>' . MonthdaySelector::PATTERN . ')' . self::WIDE_RANGE_SEPARATOR . ')?'
        . '(?:week (?<weeks>' . RangeSelector::WEEKS_PATTERN . ')' . self::WIDE_RANGE_SEPARATOR . ')?'
        . '(?:(?<weekdays>' . WeekdaySelector::PATTERN . ')' . self::SEPARATOR . ')?'
        . '(?:(?<times>' . self::TIMES . ')' . self::SEPARATOR . ')?'
        . '(?<modifier>off|closed|open)?'
        . '$~';

    /**
     * @param list<string> $hours time ranges, empty if closed
     */
    public function __construct(
        public readonly ?RangeSelector $years,
        public readonly ?MonthdaySelector $monthdays,
        public readonly ?RangeSelector $weeks,
        public readonly ?WeekdaySelector $weekdays,
        public readonly array $hours,
    ) {
    }

    public static function fromString(string $rule): self
    {
        $rule = preg_replace(['~\s+~', '~ ?, ?~'], [' ', ','], trim($rule));
        if (!preg_match(self::PATTERN, $rule, $matches, PREG_UNMATCHED_AS_NULL)) {
            throw new InvalidOsmStringException(sprintf('Invalid rule "%s".', $rule));
        }

        return new self(
            null !== $matches['years'] ? RangeSelector::years($matches['years']) : null,
            null !== $matches['monthdays'] ? MonthdaySelector::fromString($matches['monthdays']) : null,
            null !== $matches['weeks'] ? RangeSelector::weeks($matches['weeks']) : null,
            null !== $matches['weekdays'] ? WeekdaySelector::fromString($matches['weekdays']) : null,
            self::hours($rule, $matches['times'], $matches['modifier']),
        );
    }

    /**
     * @return list<Selector>
     */
    public function selectors(): array
    {
        return array_values(array_filter([$this->years, $this->monthdays, $this->weeks, $this->weekdays]));
    }

    public function matches(\DateTimeInterface $date): bool
    {
        foreach ($this->selectors() as $selector) {
            if (!$selector->matches($date)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>|null
     */
    public function __invoke(\DateTimeInterface $date): ?array
    {
        return $this->matches($date) ? $this->hours : null;
    }

    /**
     * @return list<string>
     */
    private static function hours(string $rule, ?string $times, ?string $modifier): array
    {
        if ('off' === $modifier || 'closed' === $modifier) {
            return [];
        }

        $hours = null === $times || '24/7' === $times ? ['00:00-24:00'] : explode(',', $times);

        try {
            OpeningHoursForDay::fromStrings($hours);
        } catch (OpeningHoursException $exception) {
            throw new InvalidOsmStringException(sprintf('Invalid hours in rule "%s".', $rule), 0, $exception);
        }

        return $hours;
    }
}
