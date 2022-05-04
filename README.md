# osm-opening-hours
Parser for Openstreetmap opening hours format

# Knows issues

- the `spatie/opening-hours` library does not support different settings for a weekday based on the week (like
  `week 01-53/2 Fr 09:00-12:00; week 02-52/2 Fr 14:00-18:00`), so we have to add this information via the data
  attribute which the library supports for each given opening hour value
- things like `week 14-24` and `week 1,3,7,34` are not supported yet
- the OSM input string is not validated yet
- No support for constrained weekdays yet `Th[1,2-3], Fr[-1]`
- No support for calculations yet `Sa[-1],Sa[-1] +1 day`