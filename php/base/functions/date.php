<?php

/**
 * Returns the weekday name from the ISO-8601 week day number,
 * (1 = Monday, 7 = Sunday). If $long is true, then the full name
 * is returned, otherwise the 3-letter short form is returned
 */
function weekday_name(int $daynum, bool $long = false) : string {
	assert($daynum > 0 && $daynum <= 7);
	if ($long) {
		switch($daynum) {
		default: return 'Unknown';
		case 1: return 'Monday';
		case 2: return 'Tuesday';
		case 3: return 'Wednesday';
		case 4: return 'Thursday';
		case 5: return 'Friday';
		case 6: return 'Saturday';
		case 7: return 'Sunday';
		}
	} else {
		switch($daynum) {
		default: return 'Unknown';
		case 1: return 'Mon';
		case 2: return 'Tue';
		case 3: return 'Wed';
		case 4: return 'Thu';
		case 5: return 'Fri';
		case 6: return 'Sat';
		case 7: return 'Sun';
		}
	}
}

/**
 * Workaround function for bug in HipHop DateTime::modify function
 * https://github.com/facebook/hiphop-php/issues/958
 *
 * Used for when a modify is of the format '<ordinal> <dayname> of this month'
 *
 * $format is the '<ordinal> <dayname>' of the modification string
 */
function date_modify_this_month(DateTime &$date, string $format) {
	$desc = sprintf("%s of %s %d", $format, $date->format('M'), $date->format('Y'));
	// So the DateTime class is super odd and if it's constructed with something like
	// 'first day of june 2012' then you can't change the day (but only the day)
	$d = new \DateTime($desc);
	$d->setTimezone($date->getTimezone());
	$date = \pr\base\orm\DateTimeType::fromDateTime($d);
	return $date;
}
