# Openstreetmap opening hours parser in PHP

[![Packagist](https://img.shields.io/packagist/v/ujamii/osm-opening-hours.svg?colorB=green&style=flat)](https://packagist.org/packages/ujamii/osm-opening-hours)
[![Minimum PHP Version](https://img.shields.io/badge/php-8.5%2B-8892BF.svg?style=flat)](https://php.net/)
[![Continuous Integration](https://github.com/ujamii/osm-opening-hours/actions/workflows/php.yml/badge.svg)](https://github.com/ujamii/osm-opening-hours/actions)


Parser for the [Openstreetmap opening hours format](https://wiki.openstreetmap.org/wiki/Key:opening_hours) as connector for the library 
[`spatie/opening-hours`](https://github.com/spatie/opening-hours).

## Installation

`composer require ujamii/osm-opening-hours`

## Usage

```php
$hours = OsmStringToOpeningHoursConverter::openingHoursFromOsmString('Mo-Sa 10:00-18:00');
$hours->isOpenAt(new \DateTimeImmutable('2022-01-10 16:00:00')); // true, this is a monday
$hours->isOpenAt(new \DateTimeImmutable('2022-03-06 16:00:00')); // false, as this is a sunday
```

There are a lot more methods on the `$hours` object, please check the [docs](https://github.com/spatie/opening-hours#usage) of that library.
Of course, you can also use more complex input (please also check the [docs](https://wiki.openstreetmap.org/wiki/Key:opening_hours))
and the list of missing features below to see what's actually possible:

```
Mo-Fr 08:00-12:00,13:00-17:30; Sa 08:00-12:00
Sa 08:00-12:00; Mo 11:30-17:00; Tu 11:30-18:00; Dec 23-31 off; Jan 24 off; Oct 10 off; PH off; Apr 16 off
2026-2028: Jun-Aug Mo-Fr 10:00-20:00; 2026 Dec 24-2027 Jan 06 off
week 14-24 Mo 10:00-12:00; week 1,3,7,34 Tu 10:00-12:00
Th[1,3] 18:00-22:00; Fr[-1] 10:00-12:00; Sa[-1] +1 day 10:00-16:00
```

Supported selectors:

| Selector  | Examples                                                    |
|-----------|-------------------------------------------------------------|
| Years     | `2026`, `2024-2028`, `2020-2030/2`, `2026+`                 |
| Months    | `Jan`, `Jan-Mar`, `Nov-Feb`                                 |
| Monthdays | `Dec 25`, `Dec 24-26`, `Dec 24-Jan 06`, `2026 Dec 24-2027 Jan 06` |
| Weeks     | `week 05`, `week 14-24`, `week 01-53/3`, `week 1,3,7,34`    |
| Weekdays  | `Mo`, `Mo-Fr`, `Fr-Mo`, `Mo,We,Fr`                          |
| Nth weekday of a month | `Th[1]`, `Th[1,2-3]`, `Fr[-1]`, `Sa[-1] +1 day`, `Sa[1] -2 days` |
| Times     | `10:00-12:00`, `08:00-12:00,13:00-17:30`, `24/7`            |
| Modifiers | `off`, `closed`, `open`                                     |

Later rules override earlier ones. Plain weekday rules and recurring dates are converted to weekdays and exceptions of
`spatie/opening-hours`, all other rules (and rules that may overlap with them) are converted to filters.
Filters are evaluated per date, so use date based methods like `isOpenAt()` or `forDate()`. The regular week returned
by `forWeek()` only contains the plain weekday rules.

### Validation

The conversion is lenient and skips rules it cannot parse. To check an OSM string first, use

```php
OsmStringToOpeningHoursConverter::isValid('Mo-Fr 10:00-18:00; PH off', ['PH' => new GermanPublicHolidayFilter()]); // true
OsmStringToOpeningHoursConverter::validate('Mo-Fr 10:00-18:00; foo'); // throws InvalidOsmStringException: Invalid rule "foo".
```

If you also need to support the public holiday feature (like `PH off` in the example above), you have to pass in a filter like this:

```php
$hours = OsmStringToOpeningHoursConverter::openingHoursFromOsmString('Mo-Su 10:00-18:00; PH 09:00-12:00', ['PH' => new GermanPublicHolidayFilter()]);
$hours->isOpenAt(new \DateTimeImmutable('2022-01-10 16:00:00')); // true, open late on normal day
$hours->isOpenAt(new \DateTimeImmutable('2022-01-01 16:00:00')); // false, closed late on holiday
$hours->isOpenAt(new \DateTimeImmutable('2022-01-01 11:00:00')); // true, open early on holiday
```

The filter for German holidays is [included already](src/Filters/GermanPublicHolidayFilter.php), so please take at look at this and
the [corresponding interface](src/Filters/Filter.php), if you want to implement something specific for you. The expected config array
may look like `['PH' => new GermanPublicHolidayFilter()]` where the key `PH` has to match the beginning of the ruleset in the given OSM string
and the value is an instance of your filter class. 
Input values like `PH 09:00-12:00` or `PH off` will be parsed and given to the filter in the `setOpeningHours` method. The `spatie/opening-hours`
library will call the `applyFilter(\DateTimeImmutable $date)` method internally when something like `isOpenAt` is requested.
Special filters always take precedence over the other rules.

## Running tests

You can run the tests with `composer run phpunit` or `composer run testall` for test and static analysis. 

## License and Contribution

[MIT](LICENSE)

As this is OpenSource, you are very welcome to contribute by reporting bugs, improve the code, write tests or
whatever you are able to do to improve the project. Just fork and PR.

If you want to do me a favour, buy me something from my [Amazon wishlist](https://www.amazon.de/registry/wishlist/2C7LSRMLEAD4F).

## Upgrading to 3.0

- Alternating weeks like `week 01-53/2 Fr 09:00-12:00` are converted to filters. The `data` attribute and the
  constants `WEEKS_ODD` and `WEEKS_EVEN` were removed.
- Rules with year, month, monthday, week or nth weekday selectors are converted to filters and therefore missing
  in `forWeek()`. Use `forDate()` for each day of the week you want to show instead.
- `Mo off` closes mondays, rules that cannot be parsed are skipped (use `validate()` to detect them).

## Known issues / missing features

- holidays (`PH`, `SH`) are only supported at the beginning of a rule and via special filters
- additional (`,`) and fallback (`||`) rule separators, comments, extended times like `22:00-26:00`
  and event times like `sunrise` are not supported yet
