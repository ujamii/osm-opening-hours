<?php

namespace Ujamii\OsmOpeningHours\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ujamii\OsmOpeningHours\Filters\GermanPublicHolidayFilter;
use Ujamii\OsmOpeningHours\InvalidOsmStringException;
use Ujamii\OsmOpeningHours\OsmStringToOpeningHoursConverter;
use Ujamii\OsmOpeningHours\Rule;
use Ujamii\OsmOpeningHours\Selectors\MonthdaySelector;
use Ujamii\OsmOpeningHours\Selectors\RangeSelector;
use Ujamii\OsmOpeningHours\Selectors\WeekdaySelector;

#[CoversClass(GermanPublicHolidayFilter::class)]
#[CoversClass(OsmStringToOpeningHoursConverter::class)]
#[CoversClass(Rule::class)]
#[CoversClass(MonthdaySelector::class)]
#[CoversClass(RangeSelector::class)]
#[CoversClass(WeekdaySelector::class)]
class OsmStringToOpeningHoursConverterTest extends TestCase
{
    #[DataProvider('configArrayFromOsmStringDataProvider')]
    public function testConfigArrayFromOsmString(string $osmString, array $expected, array $filters = []): void
    {
        $config = OsmStringToOpeningHoursConverter::configArrayFromOsmString($osmString, $filters);
        self::assertEquals($expected, $config);

    }

    public static function configArrayFromOsmStringDataProvider(): array
    {
        $germanPublicHolidayFilter = new GermanPublicHolidayFilter();

        return [
            'empty string'                                                                         => [
                'osmString' => '',
                'expected'  => []
            ],
            '24/7'                                                                                 => [
                'osmString' => '24/7',
                'expected'  => [
                    'monday'    => ['00:00-24:00'],
                    'tuesday'   => ['00:00-24:00'],
                    'wednesday' => ['00:00-24:00'],
                    'thursday'  => ['00:00-24:00'],
                    'friday'    => ['00:00-24:00'],
                    'saturday'  => ['00:00-24:00'],
                    'sunday'    => ['00:00-24:00'],
                ]
            ],
            'Weekend only but 24h'                                                                 => [
                'osmString' => 'Sa-Su 00:00-24:00',
                'expected'  => [
                    'saturday' => ['00:00-24:00'],
                    'sunday'   => ['00:00-24:00'],
                ]
            ],
            'Multiple lists and ranges'                                                            => [
                'osmString' => 'Mo,Th-Fr,Su 12:00-13:30,19:00-21:00; Sa 12:00-13:00,19:00-21:01',
                'expected'  => [
                    'monday'    => ['12:00-13:30', '19:00-21:00'],
                    'thursday'  => ['12:00-13:30', '19:00-21:00'],
                    'friday'    => ['12:00-13:30', '19:00-21:00'],
                    'saturday'  => ['12:00-13:00', '19:00-21:01'],
                    'sunday'    => ['12:00-13:30', '19:00-21:00'],
                ],
            ],
            'Mo-Sa 10-20; Tu 10-14'                                                                => [
                'osmString' => 'Mo-Sa 10:00-20:00; Tu 10:00-14:00',
                'expected'  => [
                    'monday'    => ['10:00-20:00'],
                    'tuesday'   => ['10:00-14:00'],
                    'wednesday' => ['10:00-20:00'],
                    'thursday'  => ['10:00-20:00'],
                    'friday'    => ['10:00-20:00'],
                    'saturday'  => ['10:00-20:00'],
                ]
            ],
            'Multiple opening intervals'                                                           => [
                'osmString' => 'Mo-Fr 08:00-12:00,13:00-17:30',
                'expected'  => [
                    'monday'    => ['08:00-12:00', '13:00-17:30'],
                    'tuesday'   => ['08:00-12:00', '13:00-17:30'],
                    'wednesday' => ['08:00-12:00', '13:00-17:30'],
                    'thursday'  => ['08:00-12:00', '13:00-17:30'],
                    'friday'    => ['08:00-12:00', '13:00-17:30'],
                ],
            ],
            'Multiple days same time periods'                                                      => [
                'osmString' => 'Mo,We 08:00-12:00',
                'expected'  => [
                    'monday'    => ['08:00-12:00'],
                    'wednesday' => ['08:00-12:00'],
                ],
            ],
            'Multiple days and multiple time periods'                                              => [
                'osmString' => 'Mo-Fr 08:00-12:00,13:00-17:30; Sa 08:00-12:00',
                'expected'  => [
                    'monday'    => ['08:00-12:00', '13:00-17:30'],
                    'tuesday'   => ['08:00-12:00', '13:00-17:30'],
                    'wednesday' => ['08:00-12:00', '13:00-17:30'],
                    'thursday'  => ['08:00-12:00', '13:00-17:30'],
                    'friday'    => ['08:00-12:00', '13:00-17:30'],
                    'saturday'  => ['08:00-12:00'],
                ],
            ],
            'Open from 09:00 to 12:00 on Fridays of odd weeks and on the Wednesdays of even weeks' => [
                'osmString' => 'week 01-53/2 Fr 09:00-12:00; week 02-52/2 We 09:00-12:00',
                'expected'  => [
                    'filters' => [Rule::fromString('week 02-52/2 We 09:00-12:00'), Rule::fromString('week 01-53/2 Fr 09:00-12:00')],
                ]
            ],
            'alternating weeks with exceptions'                                                    => [
                'osmString' => 'week 01-51/2 Sa 08:00-12:00; Mo 11:30-17:00; Tu 11:30-18:00; Dec 23-31 off; Jan 24 off; PH off; Apr 16 off',
                'expected'  => [
                    'monday'  => ['11:30-17:00'],
                    'tuesday' => ['11:30-18:00'],
                    'filters' => [
                        [$germanPublicHolidayFilter, 'applyFilter'],
                        Rule::fromString('Apr 16 off'),
                        Rule::fromString('Jan 24 off'),
                        Rule::fromString('Dec 23-31 off'),
                        Rule::fromString('week 01-51/2 Sa 08:00-12:00'),
                    ],
                ],
                'filters'   => ['PH' => $germanPublicHolidayFilter]
            ],
            'week with step 3 becomes a filter'                                                   => [
                'osmString' => 'Mo-Fr 09:00-16:00; week 01-51/3 Mo-Fr 09:00-10:00',
                'expected'  => [
                    'monday'    => ['09:00-16:00'],
                    'tuesday'   => ['09:00-16:00'],
                    'wednesday' => ['09:00-16:00'],
                    'thursday'  => ['09:00-16:00'],
                    'friday'    => ['09:00-16:00'],
                    'filters'   => [Rule::fromString('week 01-51/3 Mo-Fr 09:00-10:00')],
                ]
            ],
            'invalid statement'                                                               => [
                'osmString' => 'Mo-Fr 09:00-10:00; foo-bar off',
                'expected'  => [
                    'monday'    => ['09:00-10:00'],
                    'tuesday'   => ['09:00-10:00'],
                    'wednesday' => ['09:00-10:00'],
                    'thursday'  => ['09:00-10:00'],
                    'friday'    => ['09:00-10:00'],
                ]
            ],
            'starting_on_monday' => [
                'osmString' => 'Su-Sa 00:00-24:00',
                'expected' => [
                    'sunday'    => ['00:00-24:00'],
                    'monday'    => ['00:00-24:00'],
                    'tuesday'   => ['00:00-24:00'],
                    'wednesday' => ['00:00-24:00'],
                    'thursday'  => ['00:00-24:00'],
                    'friday'    => ['00:00-24:00'],
                    'saturday'  => ['00:00-24:00'],
                ]
            ],
            'starting_on_monday_with_off_period' => [
                'osmString' => 'Su-Tu,Sa 00:00-24:00; We-Fr off',
                'expected' => [
                    'sunday'    => ['00:00-24:00'],
                    'monday'    => ['00:00-24:00'],
                    'tuesday'   => ['00:00-24:00'],
                    'saturday'  => ['00:00-24:00'],
                ]
            ],
            'same_weekday' => [
                'osmString' => 'Su-Su 09:00-17:00',
                'expected' => [
                    'sunday'    => ['09:00-17:00'],
                ]
            ],
            'all days without weekday selector' => [
                'osmString' => '10:00-12:00',
                'expected' => [
                    'monday'    => ['10:00-12:00'],
                    'tuesday'   => ['10:00-12:00'],
                    'wednesday' => ['10:00-12:00'],
                    'thursday'  => ['10:00-12:00'],
                    'friday'    => ['10:00-12:00'],
                    'saturday'  => ['10:00-12:00'],
                    'sunday'    => ['10:00-12:00'],
                ]
            ],
            'weekday off overrides earlier rule' => [
                'osmString' => 'Mo-Fr 10:00-18:00; We-Th off',
                'expected' => [
                    'monday'    => ['10:00-18:00'],
                    'tuesday'   => ['10:00-18:00'],
                    'friday'    => ['10:00-18:00'],
                ]
            ],
            'weekday rule overlapping a filter becomes a filter' => [
                'osmString' => 'week 01-53/2 Mo,Fr 09:00-12:00; Sa 10:00-12:00; Fr closed',
                'expected' => [
                    'saturday' => ['10:00-12:00'],
                    'filters'  => [Rule::fromString('Fr closed'), Rule::fromString('week 01-53/2 Mo,Fr 09:00-12:00')],
                ],
            ],
            'weekday rule overlapping an older filter becomes a filter' => [
                'osmString' => 'week 01-53/2 Mo 09:00-12:00; week 02-52/2 Fr 09:00-12:00; Mo off',
                'expected' => [
                    'filters' => [
                        Rule::fromString('Mo off'),
                        Rule::fromString('week 02-52/2 Fr 09:00-12:00'),
                        Rule::fromString('week 01-53/2 Mo 09:00-12:00'),
                    ],
                ],
            ],
            'any rule after a filter without weekdays becomes a filter' => [
                'osmString' => 'week 01-53/2 09:00-12:00; Su off',
                'expected' => [
                    'filters' => [Rule::fromString('Su off'), Rule::fromString('week 01-53/2 09:00-12:00')],
                ],
            ],
            'any rule after an nth weekday filter becomes a filter' => [
                'osmString' => 'Sa[-1] +1 day 09:00-12:00; Su off',
                'expected' => [
                    'filters' => [Rule::fromString('Su off'), Rule::fromString('Sa[-1] +1 day 09:00-12:00')],
                ],
            ],
            'exception with hours' => [
                'osmString' => 'Dec 24 10:00-14:00',
                'expected' => ['exceptions' => ['12-24' => ['10:00-14:00']]],
            ],
            'later exception replaces earlier one' => [
                'osmString' => 'Dec 24-25 off; Dec 24 10:00-14:00',
                'expected' => ['exceptions' => ['12-24' => ['10:00-14:00'], '12-25' => []]],
            ],
            'exception across the turn of the year' => [
                'osmString' => 'Dec 31-Jan 01 off',
                'expected' => ['exceptions' => ['01-01' => [], '12-31' => []]],
            ],
            'weekday rule after an exception becomes a filter' => [
                'osmString' => 'Dec 25 off; Mo-Fr 10:00-18:00',
                'expected' => [
                    'exceptions' => ['12-25' => []],
                    'filters'    => [Rule::fromString('Mo-Fr 10:00-18:00')],
                ],
            ],
            'year selector with date becomes a filter' => [
                'osmString' => '2026: Dec 24 off',
                'expected' => ['filters' => [Rule::fromString('2026: Dec 24 off')]],
            ],
            'year bound date becomes a filter' => [
                'osmString' => '2026 Dec 24 off',
                'expected' => ['filters' => [Rule::fromString('2026 Dec 24 off')]],
            ],
            'rules after a filter become filters too, latest first' => [
                'osmString' => 'Jun-Aug Mo-Fr 10:00-20:00; Jul 04 off; Mo 08:00-09:00',
                'expected' => [
                    'filters' => [
                        Rule::fromString('Mo 08:00-09:00'),
                        Rule::fromString('Jul 04 off'),
                        Rule::fromString('Jun-Aug Mo-Fr 10:00-20:00'),
                    ],
                ],
            ],
            'special filters precede rule filters' => [
                'osmString' => 'Dec Mo 10:00-12:00; PH off',
                'expected' => [
                    'filters' => [[$germanPublicHolidayFilter, 'applyFilter'], Rule::fromString('Dec Mo 10:00-12:00')],
                ],
                'filters' => ['PH' => $germanPublicHolidayFilter],
            ],
            'second special filter matches' => [
                'osmString' => 'PH off',
                'expected' => ['filters' => [[$germanPublicHolidayFilter, 'applyFilter']]],
                'filters' => ['SH' => new GermanPublicHolidayFilter(), 'PH' => $germanPublicHolidayFilter],
            ],
            'special filter with selectors is skipped' => [
                'osmString' => 'PH Mo off',
                'expected' => [],
                'filters' => ['PH' => $germanPublicHolidayFilter],
            ],
        ];
    }

    public function testInvalidFilterObject(): void
    {
        $this->expectException(\RuntimeException::class);
        OsmStringToOpeningHoursConverter::configArrayFromOsmString('24/7', [new \DateTime()]);
    }

    #[DataProvider('openingHoursDataProvider')]
    public function testOpeningHoursFromOsmString(string $osmString, string $dateTime, bool $expected): void
    {
        $hours = OsmStringToOpeningHoursConverter::openingHoursFromOsmString($osmString);

        self::assertSame($expected, $hours->isOpenAt(new \DateTimeImmutable($dateTime)));
    }

    public static function openingHoursDataProvider(): array
    {
        return [
            'first and third thursday, first'        => ['Th[1,3] 10:00-12:00', '2026-11-05 11:00', true],
            'first and third thursday, second'       => ['Th[1,3] 10:00-12:00', '2026-11-12 11:00', false],
            'first and third thursday, third'        => ['Th[1,3] 10:00-12:00', '2026-11-19 11:00', true],
            'last friday'                            => ['Fr[-1] 10:00-12:00', '2026-11-27 11:00', true],
            'not the last friday'                    => ['Fr[-1] 10:00-12:00', '2026-11-20 11:00', false],
            'day after last saturday, next month'    => ['Sa[-1] +1 day 10:00-12:00', '2026-11-01 11:00', true],
            'day after last saturday, same month'    => ['Sa[-1] +1 day 10:00-12:00', '2026-11-29 11:00', true],
            'not the day after last saturday'        => ['Sa[-1] +1 day 10:00-12:00', '2026-10-01 11:00', false],
            'summer weekday'                         => ['Jun-Aug Mo-Fr 10:00-20:00; Jul 04 off', '2026-07-03 11:00', true],
            'summer exception'                       => ['Jun-Aug Mo-Fr 10:00-20:00; Jul 04 off', '2026-07-04 11:00', false],
            'outside of summer'                      => ['Jun-Aug Mo-Fr 10:00-20:00; Jul 04 off', '2026-09-01 11:00', false],
            'week range'                             => ['week 14-24 Mo 10:00-12:00', '2026-03-30 11:00', true],
            'outside of week range'                  => ['week 14-24 Mo 10:00-12:00', '2026-03-23 11:00', false],
            'week list'                              => ['week 1,3,7,34 Mo 10:00-12:00', '2026-01-12 11:00', true],
            'outside of week list'                   => ['week 1,3,7,34 Mo 10:00-12:00', '2026-01-05 11:00', false],
            'odd week friday, morning'               => ['week 01-53/2 Fr 09:00-12:00; week 02-52/2 Fr 14:00-18:00', '2026-11-20 10:00', true],
            'odd week friday, afternoon'             => ['week 01-53/2 Fr 09:00-12:00; week 02-52/2 Fr 14:00-18:00', '2026-11-20 15:00', false],
            'even week friday, morning'              => ['week 01-53/2 Fr 09:00-12:00; week 02-52/2 Fr 14:00-18:00', '2026-11-27 10:00', false],
            'even week friday, afternoon'            => ['week 01-53/2 Fr 09:00-12:00; week 02-52/2 Fr 14:00-18:00', '2026-11-27 15:00', true],
            'every third week'                       => ['Mo 08:00-09:00; week 01-51/3 Mo 10:00-12:00', '2026-01-19 11:00', true],
            'every third week, other week'           => ['Mo 08:00-09:00; week 01-51/3 Mo 10:00-12:00', '2026-01-12 08:30', true],
            'date range across years'                => ['2026 Dec 24-2027 Jan 06 10:00-12:00', '2027-01-06 11:00', true],
            'date range across years, other year'    => ['2026 Dec 24-2027 Jan 06 10:00-12:00', '2027-12-30 11:00', false],
            'year with step'                         => ['2025-2027/2 Mo 10:00-12:00', '2027-11-22 11:00', true],
            'year with step, skipped year'           => ['2025-2027/2 Mo 10:00-12:00', '2026-11-23 11:00', false],
            'weekday after exception still overrides' => ['Dec 25 off; Mo-Fr 10:00-18:00', '2026-12-25 11:00', true],
            'exception applies when filter does not' => ['Dec 25 10:00-12:00; Mo-Fr 10:00-18:00', '2022-12-25 11:00', true],
            'invalid rule is skipped'                => ['Mo 25:00-26:00; Mo-Fr 10:00-18:00', '2026-11-23 11:00', true],
        ];
    }

    #[DataProvider('validationDataProvider')]
    public function testIsValid(string $osmString, bool $expected): void
    {
        $filters = ['PH' => new GermanPublicHolidayFilter()];

        self::assertSame($expected, OsmStringToOpeningHoursConverter::isValid($osmString, $filters));
    }

    public static function validationDataProvider(): array
    {
        return [
            'empty string'           => ['', true],
            'weekdays'               => ['Mo-Fr 08:00-12:00,13:00-17:30; Sa 08:00-12:00', true],
            'with special filter'    => ['Mo-Fr 10:00-18:00; PH off', true],
            'all selectors'          => ['2026 Dec: week 50-52 Mo[1] 10:00-12:00', true],
            'unknown token'          => ['Mo-Fr 10:00-18:00; foo-bar off', false],
            'invalid hour'           => ['Mo 25:00-26:00', false],
            'invalid date'           => ['Feb 30 off', false],
            'invalid week'           => ['week 54 Mo off', false],
            'special filter with selectors' => ['PH Mo off', false],
        ];
    }

    public function testValidateNamesTheInvalidRule(): void
    {
        $this->expectException(InvalidOsmStringException::class);
        $this->expectExceptionMessage('Invalid rule "Mo-Fr 10:00-18:00 foo".');

        OsmStringToOpeningHoursConverter::validate('Sa 10:00-12:00; Mo-Fr 10:00-18:00 foo');
    }

    public function testValidateChecksFilterObjects(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Filter DateTime for PH needs to implement the Filter interface.');

        OsmStringToOpeningHoursConverter::validate('', ['PH' => new \DateTime()]);
    }
}
