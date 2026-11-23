<?php

namespace Ujamii\OsmOpeningHours\Selectors;

use Ujamii\OsmOpeningHours\InvalidOsmStringException;
use Ujamii\OsmOpeningHours\OsmStringToOpeningHoursConverter;

/**
 * Matches months and dates, e.g. `Jan`, `Nov-Feb`, `Dec 25`, `Dec 24-26`, `Dec 24-Jan 06` or `2024 Dec 24-2025 Jan 06`.
 * Ranges without a year repeat every year and may wrap around the turn of the year.
 */
final class MonthdaySelector implements Selector
{
    public const string MONTH = '(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)';

    private const string DATE = '(?:\d{4} )?' . self::MONTH . '(?: \d{1,2})?';
    private const string RANGE = self::DATE . '(?:-(?:' . self::DATE . '|\d{1,2}))?';

    public const string PATTERN = self::RANGE . '(?:,' . self::RANGE . ')*';

    /**
     * @param list<array{?int, int, ?int, int}> $ranges list of [fromYear, fromMonthday, toYear, toMonthday],
     *                                                  where a monthday is month * 100 + day
     */
    private function __construct(private readonly array $ranges)
    {
    }

    public static function fromString(string $list): self
    {
        $month = '(' . self::MONTH . ')';
        $pattern = "~^(?:(\d{4}) )?$month(?: (\d{1,2}))?(?:-(?:(?:(\d{4}) )?$month(?: (\d{1,2}))?|(\d{1,2})))?$~";

        $ranges = [];
        foreach (explode(',', $list) as $range) {
            if (!preg_match($pattern, $range, $matches, PREG_UNMATCHED_AS_NULL)) {
                throw new InvalidOsmStringException(sprintf('Invalid date range "%s".', $range));
            }

            [, $fromYear, $fromMonthName, $fromDay, $toYear, $toMonthName, $toDay, $toDayOnly] = $matches;
            $isRange = null !== $toMonthName || null !== $toDayOnly;
            $fromMonth = self::month($fromMonthName);
            $toMonth = self::month($toMonthName ?? $fromMonthName);
            $toDay ??= $toDayOnly ?? ($isRange || null === $fromDay ? self::lastDay($toMonth) : $fromDay);

            $from = self::monthday($range, $fromMonth, (int) ($fromDay ?? 1));
            $to = self::monthday($range, $toMonth, (int) $toDay);

            if (null === $fromYear) {
                if (null !== $toYear) {
                    throw new InvalidOsmStringException(sprintf('Date range "%s" needs a start year.', $range));
                }
                $ranges[] = [null, $from, null, $to];

                continue;
            }

            $toYear = null !== $toYear ? (int) $toYear : (int) $fromYear + ($to < $from ? 1 : 0);
            if ($toYear * 10000 + $to < $fromYear * 10000 + $from) {
                throw new InvalidOsmStringException(sprintf('Date range "%s" ends before it starts.', $range));
            }
            $ranges[] = [(int) $fromYear, $from, $toYear, $to];
        }

        return new self($ranges);
    }

    public function matches(\DateTimeInterface $date): bool
    {
        $monthday = (int) $date->format('md');
        foreach ($this->ranges as [$fromYear, $from, $toYear, $to]) {
            $matches = match (true) {
                null !== $fromYear => $fromYear * 10000 + $from <= (int) $date->format('Ymd')
                    && (int) $date->format('Ymd') <= $toYear * 10000 + $to,
                $from <= $to => $from <= $monthday && $monthday <= $to,
                default => $from <= $monthday || $monthday <= $to,
            };
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lists all matched days as `m-d` keys, or null if the selector is bound to specific years.
     *
     * @return list<string>|null
     */
    public function dayKeys(): ?array
    {
        foreach ($this->ranges as [$fromYear]) {
            if (null !== $fromYear) {
                return null;
            }
        }

        // 2000 is a leap year, so Feb 29 is included
        $keys = [];
        for ($day = new \DateTimeImmutable('2000-01-01'); '2000' === $day->format('Y'); $day = $day->modify('+1 day')) {
            if ($this->matches($day)) {
                $keys[] = $day->format('m-d');
            }
        }

        return $keys;
    }

    private static function month(string $name): int
    {
        return (int) OsmStringToOpeningHoursConverter::MONTHS[$name];
    }

    private static function lastDay(int $month): int
    {
        return cal_days_in_month(CAL_GREGORIAN, $month, 2000);
    }

    private static function monthday(string $range, int $month, int $day): int
    {
        if ($day < 1 || $day > self::lastDay($month)) {
            throw new InvalidOsmStringException(sprintf('Invalid day in date range "%s".', $range));
        }

        return $month * 100 + $day;
    }
}
