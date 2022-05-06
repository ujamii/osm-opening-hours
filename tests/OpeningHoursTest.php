<?php

namespace Ujamii\OsmOpeningHours\Tests;

use PHPUnit\Framework\TestCase;
use Spatie\OpeningHours\OpeningHours;
use Ujamii\OsmOpeningHours\Filters\GermanPublicHolidayFilter;

class OpeningHoursTest extends TestCase
{
    public function testPublicHolidaysFilterWithDefaultReturn(): void
    {
        $filter = new GermanPublicHolidayFilter();
        $hours = OpeningHours::create(['filters' => [[$filter, 'applyFilter']]]);

        self::assertFalse($hours->isOpenOn('01-01'));
        self::assertFalse($hours->isOpenOn('10-03'));
        self::assertFalse($hours->isOpenOn('12-25'));
        self::assertFalse($hours->isOpenOn('12-26'));
    }

    public function testPublicHolidaysFilterWithDefinedHours(): void
    {
        $filter = new GermanPublicHolidayFilter(['09:00-12:00']);
        $hours = OpeningHours::create(['filters' => [[$filter, 'applyFilter']]]);

        self::assertTrue($hours->isOpenOn('01-01'));
        self::assertTrue($hours->isOpenOn('10-03'));
        self::assertTrue($hours->isOpenOn('12-25'));
        self::assertTrue($hours->isOpenOn('12-26'));
    }
}
