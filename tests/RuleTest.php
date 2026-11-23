<?php

namespace Ujamii\OsmOpeningHours\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\OpeningHours\Exceptions\Exception as OpeningHoursException;
use Ujamii\OsmOpeningHours\InvalidOsmStringException;
use Ujamii\OsmOpeningHours\Rule;
use Ujamii\OsmOpeningHours\Selectors\MonthdaySelector;
use Ujamii\OsmOpeningHours\Selectors\RangeSelector;
use Ujamii\OsmOpeningHours\Selectors\WeekdaySelector;

#[CoversClass(Rule::class)]
#[CoversClass(MonthdaySelector::class)]
#[CoversClass(RangeSelector::class)]
#[CoversClass(WeekdaySelector::class)]
class RuleTest extends TestCase
{
    public function testAllSelectors(): void
    {
        $rule = Rule::fromString(" 2026-2027  Dec 24-26:  week 52 Th\t10:00-12:00,13:00-14:00 ");

        self::assertEquals(RangeSelector::years('2026-2027'), $rule->years);
        self::assertEquals(MonthdaySelector::fromString('Dec 24-26'), $rule->monthdays);
        self::assertEquals(RangeSelector::weeks('52'), $rule->weeks);
        self::assertEquals(WeekdaySelector::fromString('Th'), $rule->weekdays);
        self::assertSame(['10:00-12:00', '13:00-14:00'], $rule->hours);
        self::assertSame([$rule->years, $rule->monthdays, $rule->weeks, $rule->weekdays], $rule->selectors());
    }

    public function testYearBelongsToDateRange(): void
    {
        $rule = Rule::fromString('2026 Dec 24-2027 Jan 06 off');

        self::assertNull($rule->years);
        self::assertTrue($rule->matches(new \DateTimeImmutable('2027-01-01')));
    }

    public function testYearsBeforeMonths(): void
    {
        $rule = Rule::fromString('2026: Dec off');

        self::assertEquals(RangeSelector::years('2026'), $rule->years);
        self::assertEquals(MonthdaySelector::fromString('Dec'), $rule->monthdays);
    }

    #[DataProvider('hoursDataProvider')]
    public function testHours(string $rule, array $expected): void
    {
        self::assertSame($expected, Rule::fromString($rule)->hours);
    }

    public static function hoursDataProvider(): array
    {
        return [
            'empty rule'     => ['', ['00:00-24:00']],
            'weekdays only'  => ['Mo-Fr', ['00:00-24:00']],
            '24/7'           => ['24/7', ['00:00-24:00']],
            'open'           => ['Mo open', ['00:00-24:00']],
            'open with time' => ['Mo 10:00-12:00 open', ['10:00-12:00']],
            'off'            => ['Mo off', []],
            'closed'         => ['Mo closed', []],
            'times only'     => ['10:00-12:00', ['10:00-12:00']],
            'spaces around commas' => ['Mo , We 09:00-12:00,  15:00-17:00', ['09:00-12:00', '15:00-17:00']],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    public function testInvalid(string $rule): void
    {
        $this->expectException(InvalidOsmStringException::class);
        $this->expectExceptionMessage(sprintf('Invalid rule "%s".', $rule));

        Rule::fromString($rule);
    }

    public static function invalidDataProvider(): array
    {
        return [
            'unknown token'        => ['Mo-Fr foo'],
            'holiday'              => ['PH off'],
            'missing separator'    => ['Mo10:00-12:00'],
            'wrong order'          => ['Mo Dec 10:00-12:00'],
            'year after month'     => ['Dec 2026'],
            'colon after weekdays' => ['Mo: 10:00-12:00'],
            'year before weekday without separator' => ['2026Mo'],
        ];
    }

    #[DataProvider('invalidHoursDataProvider')]
    public function testInvalidHours(string $rule): void
    {
        try {
            Rule::fromString($rule);
            self::fail('Exception expected');
        } catch (InvalidOsmStringException $exception) {
            self::assertSame(sprintf('Invalid hours in rule "%s".', $rule), $exception->getMessage());
            self::assertInstanceOf(OpeningHoursException::class, $exception->getPrevious());
        }
    }

    public static function invalidHoursDataProvider(): array
    {
        return [
            'hour too high' => ['Mo 25:00-26:00'],
            'overlapping'   => ['Mo 10:00-12:00,11:00-13:00'],
        ];
    }

    public function testInvoke(): void
    {
        $rule = Rule::fromString('Dec Mo 10:00-12:00');

        self::assertSame(['10:00-12:00'], $rule(new \DateTimeImmutable('2026-12-07')));
        self::assertNull($rule(new \DateTimeImmutable('2026-12-08')), 'not a monday');
        self::assertNull($rule(new \DateTimeImmutable('2026-11-30')), 'not in december');
    }

    public function testSpacesAroundCommasInLists(): void
    {
        self::assertEquals(WeekdaySelector::fromString('Mo,We'), Rule::fromString('Mo, We off')->weekdays);
    }

    public function testSelectorsAreAList(): void
    {
        $rule = Rule::fromString('Dec Mo off');

        self::assertSame([$rule->monthdays, $rule->weekdays], $rule->selectors());
    }

    public function testRuleWithoutSelectorsMatchesAlways(): void
    {
        $rule = Rule::fromString('off');

        self::assertSame([], $rule->selectors());
        self::assertSame([], $rule(new \DateTimeImmutable('2026-12-07')));
    }
}
