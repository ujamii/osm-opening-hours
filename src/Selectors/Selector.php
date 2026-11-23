<?php

namespace Ujamii\OsmOpeningHours\Selectors;

interface Selector
{
    public function matches(\DateTimeInterface $date): bool;
}
