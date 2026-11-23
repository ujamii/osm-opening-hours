<?php

namespace Ujamii\OsmOpeningHours\Tests\Selectors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ujamii\OsmOpeningHours\InvalidOsmStringException;
use Ujamii\OsmOpeningHours\Selectors\RangeSelector;

#[CoversClass(RangeSelector::class)]
class RangeSelectorTest extends TestCase
{
    #[DataProvider('yearsDataProvider')]
    public function testYears(string $list, string $date, bool $expected): void
    {
        self::assertSame($expected, RangeSelector::years($list)->matches(new \DateTimeImmutable($date)));
    }

    public static function yearsDataProvider(): array
    {
        return [
            'single year'              => ['2026', '2026-06-01', true],
            'other year'               => ['2026', '2027-06-01', false],
            'range start'              => ['2024-2026', '2024-01-01', true],
            'range end'                => ['2024-2026', '2026-12-31', true],
            'before range'             => ['2024-2026', '2023-12-31', false],
            'after range'              => ['2024-2026', '2027-01-01', false],
            'step hit'                 => ['2020-2030/5', '2025-01-01', true],
            'step miss'                => ['2020-2030/5', '2026-01-01', false],
            'open end'                 => ['2024+', '2999-01-01', true],
            'before open end'          => ['2024+', '2023-01-01', false],
            'list'                     => ['2020,2026', '2026-01-01', true],
        ];
    }

    #[DataProvider('weeksDataProvider')]
    public function testWeeks(string $list, string $date, bool $expected): void
    {
        self::assertSame($expected, RangeSelector::weeks($list)->matches(new \DateTimeImmutable($date)));
    }

    public static function weeksDataProvider(): array
    {
        return [
            'single digit week'        => ['1', '2026-01-01', true],
            'two digit week'           => ['01', '2026-01-08', false],
            'iso week of previous year' => ['53', '2027-01-01', true],
            'week range'               => ['14-24', '2026-06-08', true],
            'outside of week range'    => ['14-24', '2026-06-15', false],
            'odd weeks'                => ['01-53/2', '2026-01-05', false],
            'even weeks'               => ['02-52/2', '2026-01-05', true],
            'week list'                => ['1,3,7,34', '2026-08-17', true],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    public function testInvalidRange(string $list, bool $isYear): void
    {
        $this->expectException(InvalidOsmStringException::class);

        $isYear ? RangeSelector::years($list) : RangeSelector::weeks($list);
    }

    public static function invalidDataProvider(): array
    {
        return [
            'garbage'             => ['abc', true],
            'leading garbage'     => ['x2026', true],
            'trailing garbage'    => ['2026x', true],
            'year range reversed' => ['2026-2024', true],
            'year zero'           => ['0000', true],
            'step zero'           => ['2020-2030/0', true],
            'week zero'           => ['0', false],
            'week too high'       => ['54', false],
            'week range too high' => ['50-54', false],
            'second entry broken' => ['1,x', false],
        ];
    }
}
