<?php
/**
 * Contains several functions for retrieving and manipulating calendar events and holidays.
 *
 * @package wedge
 * @copyright 2010-2011 Wedge Team, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	array getEventRange(string earliest_date, string latest_date,
			bool use_permissions = true)
		- finds all the posted calendar events within a date range.
		- both the earliest_date and latest_date should be in the standard
		  YYYY-MM-DD format.
		- censors the posted event titles.
		- uses the current user's permissions if use_permissions is true,
		  otherwise it does nothing "permission specific".
		- returns an array of contextual information if use_permissions is
		  true, and an array of the data needed to build that otherwise.

	array getHolidayRange(string earliest_date, string latest_date)
		- finds all the applicable holidays for the specified date range.
		- earliest_date and latest_date should be YYYY-MM-DD.
		- returns an array of days, which are all arrays of holiday names.

	void canLinkEvent()
		- checks if the current user can link the current topic to the
		  calendar, permissions et al.
		- this requires the calendar_post permission, a forum moderator, or a
		  topic starter.
		- expects the $topic and $board variables to be set.
		- if the user doesn't have proper permissions, an error will be shown.

	array getTodayInfo()
		- returns an array with the current date, day, month, and year.
		- takes the users time offset into account.

	array getCalendarGrid(int month, int year, array calendarOptions)
		- returns an array containing all the information needed to show a
		  calendar grid for the given month.
		- also provides information (link, month, year) about the previous and
		  next month.

	array getCalendarWeek(int month, int year, int day, array calendarOptions)
		- as for getCalendarGrid but provides information relating to the week
		  within which the passed date sits.

	array cache_getOffsetIndependentEvents(int days_to_index)
		- cache callback function used to retrieve the holidays and events
		  between now and now + days_to_index.
		- widens the search range by an extra 24 hours to support time offset
		  shifts.
		- used by the cache_getRecentEvents function to get the information
		  needed to calculate the events taking the users time offset into
		  account.

	array cache_getRecentEvents(array eventOptions)
		- cache callback function used to retrieve the upcoming holidays
		  and events within the given period, taking into account the users
		  time offset.
		- used by the board index and SSI to show the upcoming events.

	void validateEventPost()
		- checks if the calendar post was valid.

	int getEventPoster(int event_id)
		- gets the member_id of an event identified by event_id.
		- returns false if the event was not found.

	void insertEvent(array eventOptions)
		- inserts the passed event information into the calendar table.
		- allows to either set a time span (in days) or an end_date.
		- does not check any permissions of any sort.

	void modifyEvent(int event_id, array eventOptions)
		- modifies an event.
		- allows to either set a time span (in days) or an end_date.
		- does not check any permissions of any sort.

	void removeEvent(int event_id)
		- removes an event.
		- does no permission checks.
*/

// Get all events within the given time range.
function getEventRange($low_date, $high_date, $use_permissions = true)
{
	global $settings, $context;

	$low_date_time = sscanf($low_date, '%04d-%02d-%02d');
	$low_date_time = mktime(0, 0, 0, $low_date_time[1], $low_date_time[2], $low_date_time[0]);
	$high_date_time = sscanf($high_date, '%04d-%02d-%02d');
	$high_date_time = mktime(0, 0, 0, $high_date_time[1], $high_date_time[2], $high_date_time[0]);

	// Find all the calendar info...
	$result = wesql::query('
		SELECT
			cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, cal.id_topic,
			cal.id_board, b.member_groups, t.id_first_msg, t.approved, b.id_board
		FROM {db_prefix}calendar AS cal
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)
		WHERE cal.start_date <= {date:high_date}
			AND cal.end_date >= {date:low_date}' . ($use_permissions ? '
			AND (cal.id_board = {int:no_board_link} OR {query_wanna_see_board})' : ''),
		array(
			'high_date' => $high_date,
			'low_date' => $low_date,
			'no_board_link' => 0,
		)
	);
	$events = array();
	while ($row = wesql::fetch_assoc($result))
	{
		// If the attached topic is not approved then for the moment pretend it doesn't exist
		//!!! This should be fixed to show them all and then sort by approval state later?
		if (!empty($row['id_first_msg']) && $settings['postmod_active'] && !$row['approved'])
			continue;

		// Force a censor of the title - as often these are used by others.
		censorText($row['title'], $use_permissions ? false : true);

		$start_date = sscanf($row['start_date'], '%04d-%02d-%02d');
		$start_date = max(mktime(0, 0, 0, $start_date[1], $start_date[2], $start_date[0]), $low_date_time);
		$end_date = sscanf($row['end_date'], '%04d-%02d-%02d');
		$end_date = min(mktime(0, 0, 0, $end_date[1], $end_date[2], $end_date[0]), $high_date_time);

		$lastDate = '';
		for ($date = $start_date; $date <= $end_date; $date += 86400)
		{
			// Attempt to avoid DST problems.
			//!!! Resolve this properly at some point.
			if (strftime('%Y-%m-%d', $date) == $lastDate)
				$date += 3601;
			$lastDate = strftime('%Y-%m-%d', $date);

			// If we're using permissions (calendar pages?) then just ouput normal contextual style information.
			if ($use_permissions)
				$events[strftime('%Y-%m-%d', $date)][] = array(
					'id' => $row['id_event'],
					'title' => $row['title'],
					'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == MID && allowedTo('calendar_edit_own')),
					'modify_href' => SCRIPT . '?action=' . ($row['id_board'] == 0 ? 'calendar;sa=post;' : 'post;msg=' . $row['id_first_msg'] . ';topic=' . $row['id_topic'] . '.0;calendar;') . 'eventid=' . $row['id_event'] . ';' . $context['session_query'],
					'href' => $row['id_board'] == 0 ? '' : SCRIPT . '?topic=' . $row['id_topic'] . '.0',
					'link' => $row['id_board'] == 0 ? $row['title'] : '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
					'start_date' => $row['start_date'],
					'end_date' => $row['end_date'],
					'is_last' => false,
					'id_board' => $row['id_board'],
				);
			// Otherwise, this is going to be cached and the VIEWER'S permissions should apply... just put together some info.
			else
				$events[strftime('%Y-%m-%d', $date)][] = array(
					'id' => $row['id_event'],
					'title' => $row['title'],
					'topic' => $row['id_topic'],
					'msg' => $row['id_first_msg'],
					'poster' => $row['id_member'],
					'start_date' => $row['start_date'],
					'end_date' => $row['end_date'],
					'is_last' => false,
					'allowed_groups' => explode(',', $row['member_groups']),
					'id_board' => $row['id_board'],
					'href' => $row['id_topic'] == 0 ? '' : SCRIPT . '?topic=' . $row['id_topic'] . '.0',
					'link' => $row['id_topic'] == 0 ? $row['title'] : '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
					'can_edit' => false,
				);
		}
	}
	wesql::free_result($result);

	// If we're doing normal contextual data, go through and make things clear to the templates ;).
	if ($use_permissions)
	{
		foreach ($events as $mday => $array)
			$events[$mday][count($array) - 1]['is_last'] = true;
	}

	return $events;
}

// Get all holidays within the given time range.
function getHolidayRange($low_date, $high_date)
{
	global $settings, $txt;
	loadPluginLanguage('Wedge:Calendar', 'lang/CalendarHolidays');

	$holidays = array();

	// Before we go any further, let's get all the events that might be applicable.
	// No, I still have no idea why they use year 4.
	$holiday_presets = !empty($settings['cal_preset_holidays']) ? explode(',', $settings['cal_preset_holidays']) : array();
	$events = array(
		'xmas' => '0004-12-25',
		'newyear' => '0004-01-01',
		'pirate' => '0004-09-19',
		'cupid' => '0004-02-14',
		'stpat' => '0004-03-17',
		'april' => '0004-04-01',
		'earth' => '0004-04-22',
		'un' => '0004-10-24',
		'halloween' => '0004-10-31',
		'category_parents' => array(
			'mother' => array('2012-05-13', '2013-05-12', '2014-05-11', '2015-05-10', '2016-05-08', '2017-05-14', '2018-05-13', '2019-05-12', '2020-05-10'),
			'father' => array('2012-06-17', '2013-06-16', '2014-06-15', '2015-06-21', '2016-06-19', '2017-06-18', '2018-06-17', '2019-06-16', '2020-06-21'),
		),
		'category_solstice' => array(
			'vernal' => array('2012-03-20', '2013-03-20', '2014-03-20', '2015-03-20', '2016-03-19', '2017-03-20', '2018-03-20', '2019-03-20', '2020-03-19'),
			'summer' => array('2012-06-20', '2013-06-21', '2014-06-21', '2015-06-21', '2016-06-20', '2017-06-20', '2018-06-21', '2019-06-21', '2020-06-20'),
			'autumn' => array('2012-09-22', '2013-09-22', '2014-09-22', '2015-09-23', '2016-09-22', '2017-09-22', '2018-09-22', '2019-09-23', '2020-09-22'),
			'winter' => array('2012-12-21', '2013-12-21', '2014-12-21', '2015-12-21', '2016-12-21', '2017-12-21', '2018-12-21', '2019-12-21', '2020-12-21'),
		),
		'id4' => '0004-07-04',
		'5may' => '0004-05-05',
		'flag' => '0004-06-14',
		'veteran' => '0004-11-11',
		'groundhog' => '0004-02-02',
		'thanks' => array('2011-11-24', '2012-11-22', '2013-11-21', '2014-11-20', '2015-11-26', '2016-11-24', '2017-11-23', '2018-11-22', '2019-11-21', '2020-11-26'),
		'memorial' => array('2012-05-28', '2013-05-27', '2014-05-26', '2015-05-25', '2016-05-30', '2017-05-29', '2018-05-28', '2019-05-27', '2020-05-25'),
		'labor' => array('2012-09-03', '2013-09-09', '2014-09-08', '2015-09-07', '2016-09-05', '2017-09-04', '2018-09-03', '2019-09-09', '2020-09-07'),
		'dday' => '0004-06-06',
	);
	call_hook('calendar_holidays', array(&$events));
	$possible_events = array();
	$lowShort = substr($low_date, 5);
	$highShort = substr($high_date, 5);

	foreach ($events as $id => $dates)
	{
		if (!in_array($id, $holiday_presets))
			continue;
		if (!is_array($dates))
			$dates = array($dates);

		if (strpos($id, 'category_') === 0)
		{
			// So it's a group of events under a single banner.
			foreach ($dates as $cat_id => $cat_dates)
			{
				foreach ($cat_dates as $date)
				{
					$short = substr($date, 5);
					if ((strpos($date, '0004-') === 0 && $short >= $lowShort && $short <= $highShort) || ($date >= $low_date && $date <= $high_date))
					{
						if (substr($low_date, 0, 4) != substr($high_date, 0, 4))
							$event_year = $short < $highShort ? substr($high_date, 0, 4) : substr($low_date, 0, 4);
						else
							$event_year = substr($low_date, 0, 4);

						$holidays[$event_year . substr($date, 4)][] = $txt['cal_hol_' . $cat_id];
						break;
					}
				}
			}
		}
		else
		{
			// It's one event under one banner - with (possibly) multiple dates.
			foreach ($dates as $date)
			{
				$short = substr($date, 5);
				if ((strpos($date, '0004-') === 0 && $short >= $lowShort && $short <= $highShort) || ($date >= $low_date && $date <= $high_date))
				{
					if (substr($low_date, 0, 4) != substr($high_date, 0, 4))
						$event_year = $short < $highShort ? substr($high_date, 0, 4) : substr($low_date, 0, 4);
					else
						$event_year = substr($low_date, 0, 4);

					$holidays[$event_year . substr($date, 4)][] = $txt['cal_hol_' . $id];
					break;
				}
			}
		}
	}

	// Get the lowest and highest dates for "all years".
	if (substr($low_date, 0, 4) != substr($high_date, 0, 4))
		$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_dec}
			OR event_date BETWEEN {date:all_year_jan} AND {date:all_year_high}';
	else
		$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_high}';

	// Find some holidays... ;).
	$result = wesql::query('
		SELECT event_date, YEAR(event_date) AS year, title
		FROM {db_prefix}calendar_holidays
		WHERE event_date BETWEEN {date:low_date} AND {date:high_date}
			OR ' . $allyear_part,
		array(
			'low_date' => $low_date,
			'high_date' => $high_date,
			'all_year_low' => '0004' . substr($low_date, 4),
			'all_year_high' => '0004' . substr($high_date, 4),
			'all_year_jan' => '0004-01-01',
			'all_year_dec' => '0004-12-31',
		)
	);

	while ($row = wesql::fetch_assoc($result))
	{
		if (substr($low_date, 0, 4) != substr($high_date, 0, 4))
			$event_year = substr($row['event_date'], 5) < substr($high_date, 5) ? substr($high_date, 0, 4) : substr($low_date, 0, 4);
		else
			$event_year = substr($low_date, 0, 4);

		$holidays[$event_year . substr($row['event_date'], 4)][] = $row['title'];
	}
	wesql::free_result($result);

	return $holidays;
}

// Does permission checks to see if an event can be linked to a board/topic.
function canLinkEvent()
{
	global $topic, $board;

	// If you can't post, you can't link.
	isAllowedTo('calendar_post');

	// No board?  No topic?!?
	if (empty($board))
		fatal_lang_error('missing_board_id', false);
	if (empty($topic))
		fatal_lang_error('missing_topic_id', false);

	// Administrator, Moderator, or owner.  Period.
	if (!allowedTo('admin_forum') && !allowedTo('moderate_board'))
	{
		// Not admin or a moderator of this board. You better be the owner - or else.
		$result = wesql::query('
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		if ($row = wesql::fetch_assoc($result))
		{
			// Not the owner of the topic.
			if ($row['id_member_started'] != MID)
				fatal_lang_error('not_your_topic', 'user');
		}
		// Topic/Board doesn't exist.....
		else
			fatal_lang_error('calendar_no_topic', 'general');
		wesql::free_result($result);
	}
}

// Returns date information about 'today' relative to the users time offset.
function getTodayInfo()
{
	return array(
		'day' => (int) strftime('%d', forum_time()),
		'month' => (int) strftime('%m', forum_time()),
		'year' => (int) strftime('%Y', forum_time()),
		'date' => strftime('%Y-%m-%d', forum_time()),
	);
}

// Returns the information needed to show a calendar grid for the given month.
function getCalendarGrid($month, $year, $calendarOptions)
{
	global $settings, $txt;

	// Eventually this is what we'll be returning.
	$calendarGrid = array(
		'week_days' => array(),
		'weeks' => array(),
		'short_day_titles' => !empty($calendarOptions['short_day_titles']),
		'current_month' => $month,
		'current_year' => $year,
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		'show_week_links' => !empty($calendarOptions['show_week_links']),
		'previous_calendar' => array(
			'year' => $month == 1 ? $year - 1 : $year,
			'month' => $month == 1 ? 12 : $month - 1,
			'disabled' => $settings['cal_minyear'] > ($month == 1 ? $year - 1 : $year),
		),
		'next_calendar' => array(
			'year' => $month == 12 ? $year + 1 : $year,
			'month' => $month == 12 ? 1 : $month + 1,
			'disabled' => $settings['cal_maxyear'] < ($month == 12 ? $year + 1 : $year),
		),
		//!!! Better tweaks?
		'size' => isset($calendarOptions['size']) ? $calendarOptions['size'] : 'large',
		'event_types' => array('holidays', 'events'),
	);

	// Get todays date.
	$today = getTodayInfo();

	// Get information about this month.
	$month_info = array(
		'first_day' => array(
			'day_of_week' => (int) strftime('%w', mktime(0, 0, 0, $month, 1, $year)),
			'week_num' => (int) strftime('%U', mktime(0, 0, 0, $month, 1, $year)),
			'date' => strftime('%Y-%m-%d', mktime(0, 0, 0, $month, 1, $year)),
		),
		'last_day' => array(
			'day_of_month' => (int) strftime('%d', mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 0, $month == 12 ? $year + 1 : $year)),
			'date' => strftime('%Y-%m-%d', mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 0, $month == 12 ? $year + 1 : $year)),
		),
		'first_day_of_year' => (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year)),
		'first_day_of_next_year' => (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year + 1)),
	);

	// The number of days the first row is shifted to the right for the starting day.
	$nShift = $month_info['first_day']['day_of_week'];

	$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];

	// Starting any day other than Sunday means a shift...
	if (!empty($calendarOptions['start_day']))
	{
		$nShift -= $calendarOptions['start_day'];
		if ($nShift < 0)
			$nShift = 7 + $nShift;
	}

	// Number of rows required to fit the month.
	$nRows = floor(($month_info['last_day']['day_of_month'] + $nShift) / 7);
	if (($month_info['last_day']['day_of_month'] + $nShift) % 7)
		$nRows++;

	// Fetch the arrays for posted events and holidays.
	$events = $calendarOptions['show_events'] ? getEventRange($month_info['first_day']['date'], $month_info['last_day']['date']) : array();
	$holidays = $calendarOptions['show_holidays'] ? getHolidayRange($month_info['first_day']['date'], $month_info['last_day']['date']) : array();

	call_hook('calendar_grid_month', array(&$calendarGrid, &$calendarOptions, $month_info));

	// Days of the week taking into consideration that they may want it to start on any day.
	$count = $calendarOptions['start_day'];
	for ($i = 0; $i < 7; $i++)
	{
		$calendarGrid['week_days'][] = $count;
		$count++;
		if ($count == 7)
			$count = 0;
	}

	// An adjustment value to apply to all calculated week numbers.
	if (!empty($calendarOptions['show_week_num']))
	{
		// If the first day of the year is a Sunday, then there is no
		// adjustment to be made. However, if the first day of the year is not
		// a Sunday, then there is a partial week at the start of the year
		// that needs to be accounted for.
		if ($calendarOptions['start_day'] === 0)
			$nWeekAdjust = $month_info['first_day_of_year'] === 0 ? 0 : 1;
		// If we are viewing the weeks, with a starting date other than Sunday,
		// then things get complicated! Basically, as PHP is calculating the
		// weeks with a Sunday starting date, we need to take this into account
		// and offset the whole year dependant on whether the first day in the
		// year is above or below our starting date. Note that we offset by
		// two, as some of this will get undone quite quickly by the statement
		// below.
		else
			$nWeekAdjust = $calendarOptions['start_day'] > $month_info['first_day_of_year'] && $month_info['first_day_of_year'] !== 0 ? 2 : 1;

		// If our week starts on a day greater than the day the month starts
		// on, then our week numbers will be one too high. So we need to
		// reduce it by one - all these thoughts of offsets makes my head
		// hurt...
		if ($month_info['first_day']['day_of_week'] < $calendarOptions['start_day'] || $month_info['first_day_of_year'] > 4)
			$nWeekAdjust--;
	}
	else
		$nWeekAdjust = 0;

	// Iterate through each week.
	$calendarGrid['weeks'] = array();
	for ($nRow = 0; $nRow < $nRows; $nRow++)
	{
		// Start off the week - and don't let it go above 52, since that's the number of weeks in a year.
		$calendarGrid['weeks'][$nRow] = array(
			'days' => array(),
			'number' => $month_info['first_day']['week_num'] + $nRow + $nWeekAdjust
		);
		// Handle the dreaded "week 53", it can happen, but only once in a blue moon ;)
		if ($calendarGrid['weeks'][$nRow]['number'] == 53 && $nShift != 4 && $month_info['first_day_of_next_year'] < 4)
			$calendarGrid['weeks'][$nRow]['number'] = 1;

		// And figure out all the days.
		for ($nCol = 0; $nCol < 7; $nCol++)
		{
			$nDay = ($nRow * 7) + $nCol - $nShift + 1;

			if ($nDay < 1 || $nDay > $month_info['last_day']['day_of_month'])
				$nDay = 0;

			$date = sprintf('%04d-%02d-%02d', $year, $month, $nDay);

			$calendarGrid['weeks'][$nRow]['days'][$nCol] = array(
				'day' => $nDay,
				'date' => $date,
				'is_today' => $date == $today['date'],
				'is_first_day' => !empty($calendarOptions['show_week_num']) && (($month_info['first_day']['day_of_week'] + $nDay - 1) % 7 == $calendarOptions['start_day']),
				'holidays' => !empty($holidays[$date]) ? $holidays[$date] : array(),
				'events' => !empty($events[$date]) ? $events[$date] : array(),
			);
			call_hook('calendar_month_day', array(&$calendarGrid, $nRow, $nCol, $date));
		}
	}

	// Set the previous and the next month's links.
	$calendarGrid['previous_calendar']['href'] = SCRIPT . '?action=calendar;year=' . $calendarGrid['previous_calendar']['year'] . ';month=' . $calendarGrid['previous_calendar']['month'];
	$calendarGrid['previous_calendar']['title'] = $txt['months'][$calendarGrid['previous_calendar']['month']] . ' ' . $calendarGrid['previous_calendar']['year'];
	$calendarGrid['next_calendar']['href'] = SCRIPT . '?action=calendar;year=' . $calendarGrid['next_calendar']['year'] . ';month=' . $calendarGrid['next_calendar']['month'];
	$calendarGrid['next_calendar']['title'] = $txt['months'][$calendarGrid['next_calendar']['month']] . ' ' . $calendarGrid['next_calendar']['year'];

	return $calendarGrid;
}

// Returns the information needed to show a calendar for the given week.
function getCalendarWeek($month, $year, $day, $calendarOptions)
{
	global $settings;

	// Get todays date.
	$today = getTodayInfo();

	// What is the actual "start date" for the passed day.
	$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];
	$day_of_week = (int) strftime('%w', mktime(0, 0, 0, $month, $day, $year));
	if ($day_of_week != $calendarOptions['start_day'])
	{
		// Here we offset accordingly to get things to the real start of a week.
		$date_diff = $day_of_week - $calendarOptions['start_day'];
		if ($date_diff < 0)
			$date_diff += 7;
		$new_timestamp = mktime(0, 0, 0, $month, $day, $year) - $date_diff * 86400;
		$day = (int) strftime('%d', $new_timestamp);
		$month = (int) strftime('%m', $new_timestamp);
		$year = (int) strftime('%Y', $new_timestamp);
	}

	// Now start filling in the calendar grid.
	$calendarGrid = array(
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		// Previous week is easy - just step back one day.
		'previous_week' => array(
			'year' => $day == 1 ? ($month == 1 ? $year - 1 : $year) : $year,
			'month' => $day == 1 ? ($month == 1 ? 12 : $month - 1) : $month,
			'day' => $day == 1 ? 28 : $day - 1,
			'disabled' => $day < 7 && $settings['cal_minyear'] > ($month == 1 ? $year - 1 : $year),
		),
		'next_week' => array(
			'disabled' => $day > 25 && $settings['cal_maxyear'] < ($month == 12 ? $year + 1 : $year),
		),
		'event_types' => array('holidays', 'events'),
	);

	// The next week calculation requires a bit more work.
	$curTimestamp = mktime(0, 0, 0, $month, $day, $year);
	$nextWeekTimestamp = $curTimestamp + 604800;
	$calendarGrid['next_week']['day'] = (int) strftime('%d', $nextWeekTimestamp);
	$calendarGrid['next_week']['month'] = (int) strftime('%m', $nextWeekTimestamp);
	$calendarGrid['next_week']['year'] = (int) strftime('%Y', $nextWeekTimestamp);

	// Fetch the arrays for posted events and holidays.
	$startDate = strftime('%Y-%m-%d', $curTimestamp);
	$endDate = strftime('%Y-%m-%d', $nextWeekTimestamp);
	$events = $calendarOptions['show_events'] ? getEventRange($startDate, $endDate) : array();
	$holidays = $calendarOptions['show_holidays'] ? getHolidayRange($startDate, $endDate) : array();

	call_hook('calendar_grid_week', array(&$calendarGrid, &$calendarOptions, $day, $month, $year));

	// An adjustment value to apply to all calculated week numbers.
	if (!empty($calendarOptions['show_week_num']))
	{
		$first_day_of_year = (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year));
		$first_day_of_next_year = (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year + 1));
		$last_day_of_last_year = (int) strftime('%w', mktime(0, 0, 0, 12, 31, $year - 1));

		// All this is as getCalendarGrid.
		if ($calendarOptions['start_day'] === 0)
			$nWeekAdjust = $first_day_of_year === 0 && $first_day_of_year > 3 ? 0 : 1;
		else
			$nWeekAdjust = $calendarOptions['start_day'] > $first_day_of_year && $first_day_of_year !== 0 ? 2 : 1;

		$calendarGrid['week_number'] = (int) strftime('%U', mktime(0, 0, 0, $month, $day, $year)) + $nWeekAdjust;

		// If this crosses a year boundry and includes january it should be week one.
		if ((int) strftime('%Y', $curTimestamp + 518400) != $year && $calendarGrid['week_number'] > 53 && $first_day_of_next_year < 5)
			$calendarGrid['week_number'] = 1;
	}

	// This holds all the main data - there is at least one month!
	$calendarGrid['months'] = array();
	$lastDay = 99;
	$curDay = $day;
	$curDayOfWeek = $calendarOptions['start_day'];
	for ($i = 0; $i < 7; $i++)
	{
		// Have we gone into a new month (Always happens first cycle too)
		if ($lastDay > $curDay)
		{
			$curMonth = $lastDay == 99 ? $month : ($month == 12 ? 1 : $month + 1);
			$curYear = $lastDay == 99 ? $year : ($curMonth == 1 && $month == 12 ? $year + 1 : $year);
			$calendarGrid['months'][$curMonth] = array(
				'current_month' => $curMonth,
				'current_year' => $curYear,
				'days' => array(),
			);
		}

		// Add todays information to the pile!
		$date = sprintf('%04d-%02d-%02d', $curYear, $curMonth, $curDay);

		$calendarGrid['months'][$curMonth]['days'][$curDay] = array(
			'day' => $curDay,
			'day_of_week' => $curDayOfWeek,
			'date' => $date,
			'is_today' => $date == $today['date'],
			'holidays' => !empty($holidays[$date]) ? $holidays[$date] : array(),
			'events' => !empty($events[$date]) ? $events[$date] : array(),
		);

		call_hook('calendar_week_day', array(&$calendarGrid, $curMonth, $curDay));

		// Make the last day what the current day is and work out what the next day is.
		$lastDay = $curDay;
		$curTimestamp += 86400;
		$curDay = (int) strftime('%d', $curTimestamp);

		// Also increment the current day of the week.
		$curDayOfWeek = $curDayOfWeek >= 6 ? 0 : ++$curDayOfWeek;
	}

	// Set the previous and the next week's links.
	$calendarGrid['previous_week']['href'] = SCRIPT . '?action=calendar;viewweek;year=' . $calendarGrid['previous_week']['year'] . ';month=' . $calendarGrid['previous_week']['month'] . ';day=' . $calendarGrid['previous_week']['day'];
	$calendarGrid['next_week']['href'] = SCRIPT . '?action=calendar;viewweek;year=' . $calendarGrid['next_week']['year'] . ';month=' . $calendarGrid['next_week']['month'] . ';day=' . $calendarGrid['next_week']['day'];

	return $calendarGrid;
}

// Retrieve all events for the given days, independently of the user's offset.
function cache_getOffsetIndependentEvents($days_to_index)
{
	$low_date = strftime('%Y-%m-%d', forum_time(false) - 24 * 3600);
	$high_date = strftime('%Y-%m-%d', forum_time(false) + $days_to_index * 24 * 3600);

	return array(
		'data' => array(
			'holidays' => getHolidayRange($low_date, $high_date),
			'events' => getEventRange($low_date, $high_date, false),
		),
		'refresh_eval' => 'return \'' . strftime('%Y%m%d', forum_time(false)) . '\' != strftime(\'%Y%m%d\', forum_time(false))
			|| (!empty($GLOBALS[\'settings\'][\'calendar_updated\']) && ' . time() . ' < $GLOBALS[\'settings\'][\'calendar_updated\']);',
		'expires' => time() + 3600,
	);
}

// Called from the homepage to display the current day's events on it.
function cache_getRecentEvents($eventOptions)
{
	// With the 'static' cached data we can calculate the user-specific data.
	$cached_data = cache_quick_get('calendar_index', array('Wedge:Calendar', 'Subs-Calendar'), 'cache_getOffsetIndependentEvents', array($eventOptions['num_days_shown']));

	// Get the information about today (from user perspective).
	$today = getTodayInfo();

	$return_data = array(
		'calendar_holidays' => array(),
		'calendar_events' => array(),
	);

	// Set the event span to be shown in seconds.
	$days_for_index = $eventOptions['num_days_shown'] * 86400;

	// Get the current member time/date.
	$now = forum_time();

	// Holidays between now and now + days.
	for ($i = $now; $i < $now + $days_for_index; $i += 86400)
	{
		if (isset($cached_data['holidays'][strftime('%Y-%m-%d', $i)]))
			$return_data['calendar_holidays'] = array_merge($return_data['calendar_holidays'], $cached_data['holidays'][strftime('%Y-%m-%d', $i)]);
	}

	$duplicates = array();
	for ($i = $now; $i < $now + $days_for_index; $i += 86400)
	{
		// Determine the date of the current loop step.
		$loop_date = strftime('%Y-%m-%d', $i);

		// No events today? Check the next day.
		if (empty($cached_data['events'][$loop_date]))
			continue;

		// Loop through all events to add a few last-minute values.
		foreach ($cached_data['events'][$loop_date] as $ev => $event)
		{
			// Create a shortcut variable for easier access.
			$this_event =& $cached_data['events'][$loop_date][$ev];

			// Skip duplicates.
			if (isset($duplicates[$this_event['topic'] . $this_event['title']]))
			{
				unset($cached_data['events'][$loop_date][$ev]);
				continue;
			}
			else
				$duplicates[$this_event['topic'] . $this_event['title']] = true;

			// Might be set to true afterwards, depending on the permissions.
			$this_event['can_edit'] = false;
			$this_event['is_today'] = $loop_date === $today['date'];
			$this_event['date'] = $loop_date;
		}

		if (!empty($cached_data['events'][$loop_date]))
			$return_data['calendar_events'] = array_merge($return_data['calendar_events'], $cached_data['events'][$loop_date]);
	}

	for ($i = 0, $n = count($return_data['calendar_events']); $i < $n; $i++)
		$return_data['calendar_events'][$i]['is_last'] = !isset($return_data['calendar_events'][$i + 1]);

	return array(
		'data' => $return_data,
		'expires' => time() + 3600,
		'refresh_eval' => 'return \'' . strftime('%Y%m%d', forum_time(false)) . '\' != strftime(\'%Y%m%d\', forum_time(false))
			|| (!empty($GLOBALS[\'settings\'][\'calendar_updated\']) && ' . time() . ' < $GLOBALS[\'settings\'][\'calendar_updated\']);',
		'after_run' => 'calendar_after_run',
	);
}

function calendar_after_run($params)
{
	global $context, $cache_block;

	foreach ($cache_block['data']['calendar_events'] as $k => $event)
	{
		// Remove events that the user may not see or wants to ignore.
		if ((count(array_intersect(we::$user['groups'], $event['allowed_groups'])) === 0 && !allowedTo('admin_forum') && !empty($event['id_board'])) || in_array($event['id_board'], we::$user['ignoreboards']))
			unset($cache_block['data']['calendar_events'][$k]);
		else
		{
			// Whether the event can be edited depends on the permissions.
			$cache_block['data']['calendar_events'][$k]['can_edit'] = allowedTo('calendar_edit_any') || ($event['poster'] == MID && allowedTo('calendar_edit_own'));

			// The added session code makes this URL not cachable.
			$cache_block['data']['calendar_events'][$k]['modify_href'] = SCRIPT . '?action=' . ($event['topic'] == 0 ? 'calendar;sa=post;' : 'post;msg=' . $event['msg'] . ';topic=' . $event['topic'] . '.0;calendar;') . 'eventid=' . $event['id'] . ';' . $context['session_query'];
		}
	}

	if (empty($params[0]['include_holidays']))
		$cache_block['data']['calendar_holidays'] = array();
	if (empty($params[0]['include_events']))
		$cache_block['data']['calendar_events'] = array();

	$cache_block['data']['show_calendar'] = !empty($cache_block['data']['calendar_holidays']) || !empty($cache_block['data']['calendar_events']);
}

// Makes sure the calendar post is valid.
function validateEventPost()
{
	global $settings;

	if (!isset($_POST['deleteevent']))
	{
		// No month?  No year?
		if (!isset($_POST['month']))
			fatal_lang_error('event_month_missing', false);
		if (!isset($_POST['year']))
			fatal_lang_error('event_year_missing', false);

		// Check the month and year...
		if ($_POST['month'] < 1 || $_POST['month'] > 12)
			fatal_lang_error('invalid_month', false);
		if ($_POST['year'] < $settings['cal_minyear'] || $_POST['year'] > $settings['cal_maxyear'])
			fatal_lang_error('invalid_year', false);
	}

	// Make sure they're allowed to post...
	isAllowedTo('calendar_post');

	if (isset($_POST['span']))
	{
		// Make sure it's turned on and not some fool trying to trick it.
		if (empty($settings['cal_allowspan']))
			fatal_lang_error('no_span', false);
		if ($_POST['span'] < 1 || $_POST['span'] > $settings['cal_maxspan'])
			fatal_lang_error('invalid_days_numb', false);
	}

	// There is no need to validate the following values if we are just deleting the event.
	if (!isset($_POST['deleteevent']))
	{
		// No day?
		if (!isset($_POST['day']))
			fatal_lang_error('event_day_missing', false);
		if (!isset($_POST['evtitle']) && !isset($_POST['subject']))
			fatal_lang_error('event_title_missing', false);
		elseif (!isset($_POST['evtitle']))
			$_POST['evtitle'] = $_POST['subject'];

		// Bad day?
		if (!checkdate($_POST['month'], $_POST['day'], $_POST['year']))
			fatal_lang_error('invalid_date', false);

		// No title?
		if (westr::htmltrim($_POST['evtitle']) === '')
			fatal_lang_error('no_event_title', false);
		if (westr::strlen($_POST['evtitle']) > 30)
			$_POST['evtitle'] = westr::substr($_POST['evtitle'], 0, 30);
		$_POST['evtitle'] = str_replace(';', '', $_POST['evtitle']);
	}
}

// Get the event's poster.
function getEventPoster($event_id)
{
	// A simple database query, how hard can that be?
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}calendar
		WHERE id_event = {int:id_event}
		LIMIT 1',
		array(
			'id_event' => $event_id,
		)
	);

	// No results, return false.
	if (wesql::num_rows === 0)
		return false;

	// Grab the results and return.
	list ($poster) = wesql::fetch_row($request);
	wesql::free_result($request);
	return $poster;
}

// Consolidating the various INSERT statements into this function.
function insertEvent(&$eventOptions)
{
	// Add special chars to the title.
	$eventOptions['title'] = westr::htmlspecialchars($eventOptions['title'], ENT_QUOTES);

	// Add some sanity checking to the span.
	$eventOptions['span'] = isset($eventOptions['span']) && $eventOptions['span'] > 0 ? (int) $eventOptions['span'] : 0;

	// Make sure the start date is in ISO order.
	if (($num_results = sscanf($eventOptions['start_date'], '%d-%d-%d', $year, $month, $day)) !== 3)
		trigger_error('modifyEvent(): invalid start date format given', E_USER_ERROR);

	// Set the end date (if not yet given)
	if (!isset($eventOptions['end_date']))
		$eventOptions['end_date'] = strftime('%Y-%m-%d', mktime(0, 0, 0, $month, $day, $year) + $eventOptions['span'] * 86400);

	// If no topic and board are given, they are not linked to a topic.
	$eventOptions['board'] = isset($eventOptions['board']) ? (int) $eventOptions['board'] : 0;
	$eventOptions['topic'] = isset($eventOptions['topic']) ? (int) $eventOptions['topic'] : 0;

	// Insert the event!
	wesql::insert('',
		'{db_prefix}calendar',
		array(
			'id_board' => 'int', 'id_topic' => 'int', 'title' => 'string-60', 'id_member' => 'int',
			'start_date' => 'date', 'end_date' => 'date',
		),
		array(
			$eventOptions['board'], $eventOptions['topic'], $eventOptions['title'], $eventOptions['member'],
			$eventOptions['start_date'], $eventOptions['end_date'],
		),
		array('id_event')
	);

	// Store the just inserted id_event for future reference.
	$eventOptions['id'] = wesql::insert_id();

	// Update the settings to show something calendarish was updated.
	updateSettings(array(
		'calendar_updated' => time(),
	));
}

function modifyEvent($event_id, &$eventOptions)
{
	// Properly sanitize the title.
	$eventOptions['title'] = westr::htmlspecialchars($eventOptions['title'], ENT_QUOTES);

	// Scan the start date for validity and get its components.
	if (($num_results = sscanf($eventOptions['start_date'], '%d-%d-%d', $year, $month, $day)) !== 3)
		trigger_error('modifyEvent(): invalid start date format given', E_USER_ERROR);

	// Default span to 0 days.
	$eventOptions['span'] = isset($eventOptions['span']) ? (int) $eventOptions['span'] : 0;

	// Set the end date to the start date + span (if the end date wasn't already given).
	if (!isset($eventOptions['end_date']))
		$eventOptions['end_date'] = strftime('%Y-%m-%d', mktime(0, 0, 0, $month, $day, $year) + $eventOptions['span'] * 86400);

	wesql::query('
		UPDATE {db_prefix}calendar
		SET
			start_date = {date:start_date},
			end_date = {date:end_date},
			title = SUBSTRING({string:title}, 1, 60),
			id_board = {int:id_board},
			id_topic = {int:id_topic}
		WHERE id_event = {int:id_event}',
		array(
			'start_date' => $eventOptions['start_date'],
			'end_date' => $eventOptions['end_date'],
			'title' => $eventOptions['title'],
			'id_board' => isset($eventOptions['board']) ? (int) $eventOptions['board'] : 0,
			'id_topic' => isset($eventOptions['topic']) ? (int) $eventOptions['topic'] : 0,
			'id_event' => $event_id,
		)
	);

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

function removeEvent($event_id)
{
	wesql::query('
		DELETE FROM {db_prefix}calendar
		WHERE id_event = {int:id_event}',
		array(
			'id_event' => $event_id,
		)
	);

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

function getEventProperties($event_id)
{
	$request = wesql::query('
		SELECT
			c.id_event, c.id_board, c.id_topic, MONTH(c.start_date) AS month,
			DAYOFMONTH(c.start_date) AS day, YEAR(c.start_date) AS year,
			(TO_DAYS(c.end_date) - TO_DAYS(c.start_date)) AS span, c.id_member, c.title,
			t.id_first_msg, t.id_member_started
		FROM {db_prefix}calendar AS c
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = c.id_topic)
		WHERE c.id_event = {int:id_event}',
		array(
			'id_event' => $event_id,
		)
	);

	// If nothing returned, we are in poo, poo.
	if (wesql::num_rows($request) === 0)
		return false;

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	$return_value = array(
		'boards' => array(),
		'board' => $row['id_board'],
		'new' => 0,
		'eventid' => $event_id,
		'year' => $row['year'],
		'month' => $row['month'],
		'day' => $row['day'],
		'title' => $row['title'],
		'span' => 1 + $row['span'],
		'member' => $row['id_member'],
		'topic' => array(
			'id' => $row['id_topic'],
			'member_started' => $row['id_member_started'],
			'first_msg' => $row['id_first_msg'],
		),
	);

	$return_value['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $return_value['month'] == 12 ? 1 : $return_value['month'] + 1, 0, $return_value['month'] == 12 ? $return_value['year'] + 1 : $return_value['year']));

	return $return_value;
}

function list_getHolidays($start, $items_per_page, $sort)
{
	$request = wesql::query('
		SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
		FROM {db_prefix}calendar_holidays
		ORDER BY {raw:sort}
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'sort' => $sort,
		)
	);
	$holidays = array();
	while ($row = wesql::fetch_assoc($request))
		$holidays[] = $row;
	wesql::free_result($request);

	return $holidays;
}

function list_getNumHolidays()
{
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}calendar_holidays',
		array(
		)
	);
	list ($num_items) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $num_items;
}

function removeHolidays($holiday_ids)
{
	wesql::query('
		DELETE FROM {db_prefix}calendar_holidays
		WHERE id_holiday IN ({array_int:id_holiday})',
		array(
			'id_holiday' => $holiday_ids,
		)
	);

	updateSettings(array(
		'calendar_updated' => time(),
	));
}

?>