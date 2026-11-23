<?php

namespace Ujamii\OsmOpeningHours\Tests\Selectors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ujamii\OsmOpeningHours\InvalidOsmStringException;
use Ujamii\OsmOpeningHours\Selectors\MonthdaySelector;

#[CoversClass(MonthdaySelector::class)]
class MonthdaySelectorTest extends TestCase
{
    #[DataProvider('matchesDataProvider')]
    public function testMatches(string $list, string $date, bool $expected): void
    {
        self::assertSame($expected, MonthdaySelector::fromString($list)->matches(new \DateTimeImmutable($date)));
    }

    public static function matchesDataProvider(): array
    {
        return [
            'whole month, first day'    => ['Feb', '2026-02-01', true],
            'whole month, last day'     => ['Feb', '2024-02-29', true],
            'whole month, next month'   => ['Feb', '2026-03-01', false],
            'month range'               => ['Jan-Mar', '2026-03-31', true],
            'month range, after'        => ['Jan-Mar', '2026-04-01', false],
            'wrapping month range'      => ['Nov-Feb', '2026-01-15', true],
            'wrapping month range, end' => ['Nov-Feb', '2026-02-28', true],
            'wrapping month range, out' => ['Nov-Feb', '2026-03-01', false],
            'wrapping month range, before' => ['Nov-Feb', '2026-10-31', false],
            'single day'                => ['Dec 25', '2026-12-25', true],
            'single day, next day'      => ['Dec 25', '2026-12-26', false],
            'single digit day'          => ['Jan 6', '2026-01-06', true],
            'day range'                 => ['Dec 24-26', '2026-12-26', true],
            'day range, before'         => ['Dec 24-26', '2026-12-23', false],
            'day range, after'          => ['Dec 24-26', '2026-12-27', false],
            'date range across months'  => ['Mar 30-Apr 02', '2026-04-01', true],
            'date range across years'   => ['Dec 24-Jan 06', '2027-01-06', true],
            'date range across years, out' => ['Dec 24-Jan 06', '2027-01-07', false],
            'day to end of month'       => ['Dec 24-Jan', '2027-01-31', true],
            'month to day'              => ['Jan-Feb 10', '2026-02-11', false],
            'list'                      => ['Jan 01,Dec 25', '2026-12-25', true],
            'year'                      => ['2026 Dec 24', '2026-12-24', true],
            'year, other year'          => ['2026 Dec 24', '2027-12-24', false],
            'year range across years'   => ['2026 Dec 24-Jan 06', '2027-01-06', true],
            'year range across years, start' => ['2026 Dec 24-Jan 06', '2026-12-24', true],
            'year range across years, before' => ['2026 Dec 24-Jan 06', '2026-12-23', false],
            'year range across years, out' => ['2026 Dec 24-Jan 06', '2026-01-06', false],
            'explicit end year'         => ['2026 Dec 24-2028 Jan 06', '2027-06-01', true],
            'explicit end year, after'  => ['2026 Dec 24-2028 Jan 06', '2028-01-07', false],
        ];
    }

    public function testDayKeys(): void
    {
        self::assertSame(['01-01', '02-28', '02-29', '12-31'], MonthdaySelector::fromString('Feb 28-29,Dec 31-Jan 01')->dayKeys());
    }

    public function testDayKeysOfWholeYear(): void
    {
        self::assertCount(366, MonthdaySelector::fromString('Jan-Dec')->dayKeys() ?? []);
    }

    public function testNoDayKeysWhenBoundToYear(): void
    {
        self::assertNull(MonthdaySelector::fromString('Dec 24,2026 Dec 25')->dayKeys());
    }

    #[DataProvider('invalidDataProvider')]
    public function testInvalid(string $list, string $message): void
    {
        $this->expectException(InvalidOsmStringException::class);
        $this->expectExceptionMessage($message);

        MonthdaySelector::fromString($list);
    }

    public static function invalidDataProvider(): array
    {
        return [
            'unknown month'          => ['Foo 01', 'Invalid date range "Foo 01".'],
            'day zero'               => ['Dec 00', 'Invalid day in date range "Dec 00".'],
            'day too high'           => ['Feb 30', 'Invalid day in date range "Feb 30".'],
            'end day too high'       => ['Feb 01-30', 'Invalid day in date range "Feb 01-30".'],
            'end year without start' => ['Dec 24-2027 Jan 06', 'Date range "Dec 24-2027 Jan 06" needs a start year.'],
            'end before start'       => ['2027 Jan 06-2026 Dec 24', 'Date range "2027 Jan 06-2026 Dec 24" ends before it starts.'],
        ];
    }

    public function testSameDayWithExplicitYearsIsValid(): void
    {
        self::assertTrue(MonthdaySelector::fromString('2026 Dec 24-2026 Dec 24')->matches(new \DateTimeImmutable('2026-12-24')));
    }
}
