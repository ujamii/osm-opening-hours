<?php

namespace Ujamii\OsmOpeningHours\Tests\Filters;

use PHPUnit\Framework\TestCase;
use Ujamii\OsmOpeningHours\Converter;
use Ujamii\OsmOpeningHours\Filters\GermanPublicHolidayFilter;

class GermanPublicHolidayFilterTest extends TestCase
{
    public function testFilterWithTimespanGetsApplied(): void
    {
        $hours = Converter::openingHoursFromOsmString('Mo-Su 10:00-18:00; PH 09:00-12:00', ['PH' => new GermanPublicHolidayFilter()]);

        self::assertTrue($hours->isOpenAt(new \DateTimeImmutable('2022-01-10 16:00:00')), 'open late on normal day');
        self::assertFalse($hours->isOpenAt(new \DateTimeImmutable('2022-01-01 16:00:00')), 'closed late on holiday');
        self::assertTrue($hours->isOpenAt(new \DateTimeImmutable('2022-01-01 11:00:00')), 'open early on holiday');
    }

    public function testFilterWhenClosedGetsApplied(): void
    {
        $hours = Converter::openingHoursFromOsmString('Mo-Su 10:00-18:00; PH off', ['PH' => new GermanPublicHolidayFilter()]);

        self::assertTrue($hours->isOpenAt(new \DateTimeImmutable('2022-01-10 16:00:00')), 'open late on normal day');
        self::assertFalse($hours->isOpenAt(new \DateTimeImmutable('2022-01-01 16:00:00')), 'closed late on holiday');
        self::assertFalse($hours->isOpenAt(new \DateTimeImmutable('2022-01-01 11:00:00')), 'closed early on holiday');
        self::assertFalse($hours->isOpenAt(new \DateTimeImmutable('2022-12-25')), 'closed at christmas day');
        self::assertFalse($hours->isOpenAt(new \DateTimeImmutable('2022-12-26')), 'closed at boxing day');
    }
}
