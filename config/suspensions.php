<?php
	# enables or disables suspensions
	define('ENABLE_SUSPENSIONS', true);

	# defines whether outdated suspensions should be deleted or kept for future reference
	define('CLEAR_SUSPENSIONS', true);
	# If set to false, timed out or disabled suspensions are only marked as cleared, but not deleted.
	# Otherwise, they will entirely be removed from the table.
?>