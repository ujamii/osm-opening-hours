<?php

namespace Ujamii\OsmOpeningHours\Selectors;

use Ujamii\OsmOpeningHours\OsmStringToOpeningHoursConverter;

/**
 * Matches weekdays (`Mo`, `Mo-Fr`, `Fr-Mo`), the nth weekday of a month (`Th[1,2-3]`, `Fr[-1]`)
 * and days shifted from it (`Sa[-1] +1 day`).
 */
final class WeekdaySelector implements Selector
{
    private const string DAY = '(?:Mo|Tu|We|Th|Fr|Sa|Su)';
    private const string NTH = '-?[1-5](?:--?[1-5])?';
    private const string RANGE = self::DAY . '(?:-' . self::DAY . '|\[' . self::NTH . '(?:,' . self::NTH . ')*\](?: [+-]\d+ days?)?)?';

    public const string PATTERN = self::RANGE . '(?:,' . self::RANGE . ')*';

    /**
     * @param list<int> $days ISO-8601 weekday numbers
     * @param list<array{int, list<int>, int}> $nthDays list of [weekday, nths, day offset]
     */
    private function __construct(
        public readonly array $days,
        private readonly array $nthDays,
    ) {
    }

    public static function fromString(string $list): self
    {
        $day = '(' . self::DAY . ')';
        preg_match_all(
            "~$day(?:-$day|\[([^]]+)](?: ([+-]\d+) days?)?)?~",
            $list,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        $days = [];
        $nthDays = [];
        foreach ($matches as [, $from, $to, $nths, $offset]) {
            $weekday = self::weekday($from);
            if (null !== $nths) {
                $nthDays[] = [$weekday, self::nths($nths), (int) $offset];

                continue;
            }

            $last = self::weekday($to ?? $from);
            for ($current = $weekday; $current !== $last; $current = $current % 7 + 1) {
                $days[] = $current;
            }
            $days[] = $last;
        }

        return new self($days, $nthDays);
    }

    public function isPlain(): bool
    {
        return [] === $this->nthDays;
    }

    public function matches(\DateTimeInterface $date): bool
    {
        if (in_array((int) $date->format('N'), $this->days, true)) {
            return true;
        }

        foreach ($this->nthDays as [$weekday, $nths, $offset]) {
            $day = \DateTimeImmutable::createFromInterface($date)->modify(sprintf('%+d days', -$offset));
            if ((int) $day->format('N') !== $weekday) {
                continue;
            }

            $dayOfMonth = (int) $day->format('j');
            $fromStart = intdiv($dayOfMonth - 1, 7) + 1;
            $fromEnd = -intdiv((int) $day->format('t') - $dayOfMonth, 7) - 1;
            if (in_array($fromStart, $nths, true) || in_array($fromEnd, $nths, true)) {
                return true;
            }
        }

        return false;
    }

    private static function weekday(string $name): int
    {
        return (int) array_search($name, array_keys(OsmStringToOpeningHoursConverter::WEEKDAYS), true) + 1;
    }

    /**
     * @return list<int>
     */
    private static function nths(string $list): array
    {
        $nths = [];
        foreach (explode(',', $list) as $entry) {
            preg_match('~(-?\d)(?:-(-?\d))?~', $entry, $matches, PREG_UNMATCHED_AS_NULL);
            array_push($nths, ...range((int) $matches[1], (int) ($matches[2] ?? $matches[1])));
        }

        return $nths;
    }
}
