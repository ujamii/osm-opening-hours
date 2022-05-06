<?php

namespace Ujamii\OsmOpeningHours\Filters;

class GermanPublicHolidayFilter implements Filter
{
    public function __construct(private array $openingHours = [])
    {
    }

    public function setOpeningHours(array $openingHours = []): self
    {
        $this->openingHours = $openingHours;

        return $this;
    }

    public function applyFilter(\DateTimeImmutable $date): ?array
    {
        $year       = (int)$date->format('Y');
        $easterDays = easter_days($year);
        $holidays   = [
            new \DateTimeImmutable("$year-01-01"),
            new \DateTimeImmutable("$year-03-21 +" . ($easterDays - 2) . 'days'),
            new \DateTimeImmutable("$year-03-21 +" . ($easterDays + 1) . 'days'),
            new \DateTimeImmutable("$year-03-21 +" . ($easterDays + 39) . 'days'),
            new \DateTimeImmutable("$year-03-21 +" . ($easterDays + 50) . 'days'),
            new \DateTimeImmutable("$year-05-01"),
            new \DateTimeImmutable("$year-10-03"),
            new \DateTimeImmutable("$year-12-25"),
            new \DateTimeImmutable("$year-12-26"),
        ];

        $dateToCheck = $date->format('m-d');
        foreach ($holidays as $holiday) {
            if ($dateToCheck === $holiday->format('m-d')) {
                return $this->openingHours;
                // Any valid exception-array can be returned here (range of hours, with or without data)
            }
        }

        return null;
    }
}
