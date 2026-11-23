<?php

namespace Ujamii\OsmOpeningHours\Selectors;

use Ujamii\OsmOpeningHours\InvalidOsmStringException;

/**
 * Matches years (`2024`, `2020-2030/2`, `2025+`) or ISO weeks (`05`, `01-53/2`, `1,3,7,34`).
 */
final class RangeSelector implements Selector
{
    private const string YEAR_RANGE = '\d{4}(?:-\d{4}(?:/\d+)?|\+)?';
    private const string WEEK_RANGE = '\d{1,2}(?:-\d{1,2}(?:/\d+)?)?';

    public const string YEARS_PATTERN = self::YEAR_RANGE . '(?:,' . self::YEAR_RANGE . ')*';
    public const string WEEKS_PATTERN = self::WEEK_RANGE . '(?:,' . self::WEEK_RANGE . ')*';

    /**
     * @param string $format date() format character the ranges apply to
     * @param list<array{int, int, int}> $ranges list of [from, to, step]
     */
    private function __construct(
        private readonly string $format,
        private readonly array $ranges,
    ) {
    }

    public static function years(string $list): self
    {
        return self::fromList('Y', $list, 1, 9999);
    }

    public static function weeks(string $list): self
    {
        return self::fromList('W', $list, 1, 53);
    }

    public function matches(\DateTimeInterface $date): bool
    {
        $value = (int) $date->format($this->format);
        foreach ($this->ranges as [$from, $to, $step]) {
            if ($from <= $value && $value <= $to && 0 === ($value - $from) % $step) {
                return true;
            }
        }

        return false;
    }

    private static function fromList(string $format, string $list, int $min, int $max): self
    {
        $ranges = [];
        foreach (explode(',', $list) as $range) {
            if (!preg_match('~^(\d+)(?:-(\d+)(?:/(\d+))?|(\+))?$~', $range, $matches, PREG_UNMATCHED_AS_NULL)) {
                throw new InvalidOsmStringException(sprintf('Invalid range "%s".', $range));
            }

            $from = (int) $matches[1];
            $to = null !== $matches[4] ? $max : (int) ($matches[2] ?? $from);
            $step = (int) ($matches[3] ?? 1);
            if ($from < $min || $to > $max || $from > $to || $step < 1) {
                throw new InvalidOsmStringException(sprintf('Invalid range "%s".', $range));
            }

            $ranges[] = [$from, $to, $step];
        }

        return new self($format, $ranges);
    }
}
