<?php
	# enables or disables suspensions
	define('ENABLE_SUSPENSIONS', true);

	# defines whether outdated suspensions should be deleted or kept for future reference
	define('CLEAR_SUSPENSIONS', true);
	# If set to false, timed out or disabled suspensions are only marked as cleared, but not deleted.
	# Otherwise, they will entirely be removed from the table.

	# defines in which unit suspension times are stored
	define('SUSPENSION_INTERVAL_UNIT', 'HOUR');
	# This can be one of the following: SECOND, MINUTE, HOUR, DAY, MONTH, YEAR.
	# As suspension time is stored as an integer, this also defines the minimum suspension time.
	# Whenever changing this, remember to update any existing suspension DB table entries accordingly.
?>