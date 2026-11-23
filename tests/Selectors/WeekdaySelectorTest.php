<?php

namespace Ujamii\OsmOpeningHours\Tests\Selectors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ujamii\OsmOpeningHours\Selectors\WeekdaySelector;

#[CoversClass(WeekdaySelector::class)]
class WeekdaySelectorTest extends TestCase
{
    #[DataProvider('daysDataProvider')]
    public function testDays(string $list, array $expected): void
    {
        $selector = WeekdaySelector::fromString($list);

        self::assertSame($expected, $selector->days);
        self::assertTrue($selector->isPlain());
    }

    public static function daysDataProvider(): array
    {
        return [
            'single day'    => ['We', [3]],
            'range'         => ['Mo-Fr', [1, 2, 3, 4, 5]],
            'wrapping range' => ['Fr-Mo', [5, 6, 7, 1]],
            'same day range' => ['Su-Su', [7]],
            'list'          => ['Mo,Th-Fr,Su', [1, 4, 5, 7]],
        ];
    }

    #[DataProvider('matchesDataProvider')]
    public function testMatches(string $list, string $date, bool $expected): void
    {
        self::assertSame($expected, WeekdaySelector::fromString($list)->matches(new \DateTimeImmutable($date)));
    }

    public static function matchesDataProvider(): array
    {
        // November 2026 starts on a Sunday and has 30 days
        return [
            'plain day'                 => ['Mo', '2026-11-23', true],
            'other plain day'           => ['Mo', '2026-11-24', false],
            'first'                     => ['Su[1]', '2026-11-01', true],
            'not first'                 => ['Su[1]', '2026-11-08', false],
            'second and third'          => ['Mo[2-3]', '2026-11-16', true],
            'second and third, first'   => ['Mo[2-3]', '2026-11-02', false],
            'second and third, fourth'  => ['Mo[2-3]', '2026-11-23', false],
            'fifth'                     => ['Su[5]', '2026-11-29', true],
            'last'                      => ['Mo[-1]', '2026-11-30', true],
            'not last'                  => ['Mo[-1]', '2026-11-23', false],
            'second to last'            => ['Mo[-2]', '2026-11-23', true],
            'second to last, last'      => ['Mo[-2]', '2026-11-30', false],
            'last on last day'          => ['Mo[-1]', '2026-11-30', true],
            'last, a week before end'   => ['Tu[-1]', '2026-11-24', true],
            'last two'                  => ['Mo[-2--1]', '2026-11-16', false],
            'list of nths'              => ['Th[1,3]', '2026-11-19', true],
            'list of nths, miss'        => ['Th[1,3]', '2026-11-12', false],
            'other weekday'             => ['Th[1]', '2026-11-06', false],
            'offset into next month'    => ['Sa[-1] +1 day', '2026-11-01', true],
            'offset within month'       => ['Sa[-1] +1 day', '2026-11-29', true],
            'offset, wrong day'         => ['Sa[-1] +1 day', '2026-11-28', false],
            'negative offset'           => ['Sa[1] -2 days', '2026-11-05', true],
            'negative offset, miss'     => ['Sa[1] -2 days', '2026-11-07', false],
            'mixed list'                => ['Mo,Fr[-1]', '2026-11-27', true],
            'list of nth days'          => ['Mo[1],Th[1]', '2026-11-05', true],
        ];
    }

    public function testNthIsNotPlain(): void
    {
        $selector = WeekdaySelector::fromString('Fr[-1],Mo');

        self::assertFalse($selector->isPlain());
        self::assertSame([1], $selector->days);
    }

    public function testMatchesMutableDate(): void
    {
        $date = new \DateTime('2026-11-01');

        self::assertTrue(WeekdaySelector::fromString('Sa[-1] +1 day')->matches($date));
        self::assertSame('2026-11-01', $date->format('Y-m-d'));
    }
}
