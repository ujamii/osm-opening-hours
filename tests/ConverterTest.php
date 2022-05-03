<?php

namespace Ujamii\OsmOpeningHours\Tests;

use PHPUnit\Framework\TestCase;
use Ujamii\OsmOpeningHours\Converter;

class ConverterTest extends TestCase
{
    /** @dataProvider configArrayFromOsmStringDataProvider */
    public function testConfigArrayFromOsmString(string $osmString, array $expected): void
    {
        $config = Converter::configArrayFromOsmString($osmString);
        self::assertEquals($expected, $config);
    }

    public function configArrayFromOsmStringDataProvider(): array
    {
        return [
            'empty string' => [
                'osmString' => '',
                'expected' => []
            ],
            '24/7' => [
                'osmString' => '24/7',
                'expected' => [
                    'monday' => ['00:00-24:00'],
                    'tuesday' => ['00:00-24:00'],
                    'wednesday' => ['00:00-24:00'],
                    'thursday' => ['00:00-24:00'],
                    'friday' => ['00:00-24:00'],
                    'saturday' => ['00:00-24:00'],
                    'sunday' => ['00:00-24:00'],
                ]
            ],
            'Weekend only but 24h' => [
                'osmString' => 'Sa-Su 00:00-24:00',
                'expected' => [
                    'saturday' => ['00:00-24:00'],
                    'sunday' => ['00:00-24:00'],
                ]
            ],
            'Mo-Sa 10:00-20:00; Tu 10:00-14:00' => [
                'osmString' => 'Mo-Sa 10:00-20:00; Tu 10:00-14:00',
                'expected' => [
                    'monday' => ['10:00-20:00'],
                    'tuesday' => ['10:00-14:00'],
                    'wednesday' => ['10:00-20:00'],
                    'thursday' => ['10:00-20:00'],
                    'friday' => ['10:00-20:00'],
                    'saturday' => ['10:00-20:00'],
                ]
            ],
            //'Open from 09:00 to 12:00 on Fridays of odd weeks and on the Wednesdays of even weeks' => [
            //    'osmString' => 'week 1-53/2 Fr 09:00-12:00; week 2-52/2 We 09:00-12:00',
            //    'expected' => []
            //],
            //'alternating weeks with exceptions' => [
            //    'osmString' => 'week 01-51/2 Sa 08:00-12:00; Mo 11:30-17:00; Tu 11:30-18:00; Dec 23-31 off; Jan 24 off; Oct 10 off; PH off; Apr 16 off',
            //    'expected' => []
            //],
        ];
    }

}