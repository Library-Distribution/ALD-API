<?php
	# enables or disables suspensions
	define('ENABLE_SUSPENSIONS', true);

	# defines whether outdated suspensions should be deleted or kept for future reference
	define('CLEAR_SUSPENSIONS', true);
	# If set to false, timed out or disabled suspensions are only marked as cleared, but not deleted.
	# Otherwise, they will entirely be removed from the table.

	# The subject line of the mail sent to a user when he changes his mail address
	define('MAIL_CHANGE_SUBJECT', 'libba.net - email address validation');

	# A template for the mail to be sent to the user when he attempts to change his mail address.
	define('MAIL_CHANGE_TEMPLATE', 'You have changed your email address to "$mail". Until it has been validated that this email address belongs to you,' . PHP_EOL
							. 'your account has been suspended. To unsuspend it, go to <a href="http://libba.net/users/$user/unsuspend/$suspension">this page</a>.');
	# The following variables are available:
	# * {$USER} - the user's name
	# * {$ID} - the user's ID
	# * {$MAIL} - the new mail address
	# * {$SUSPENSION} - the ID of the created suspension
?>