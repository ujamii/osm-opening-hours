<?php

namespace Ujamii\OsmOpeningHours\Filters;

interface Filter
{
    public function setOpeningHours(array $openingHours = []): GermanPublicHolidayFilter;

    public function applyFilter(\DateTimeImmutable $date): ?array;
}
