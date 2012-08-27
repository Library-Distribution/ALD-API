<?php
	# the time interval after which a registration request times out
	define('REGISTRATION_TIMEOUT', '30 MINUTE');
	# The value must follow the rules of the MySQL DATE_ADD() function (`expr` followed by `unit`)
	# (http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-add).

	# a list of URLs to POST data to after a successful registration, separated by space
	define('POST_REGISTRATION_URLS', '');
	# each of these URLs receives the following POST data:
	#   + user name ('user')
	#   + mail address ('mail')
	#   + password (cleartext) ('password')
	#   + id ('id')
	# The words in brackets & quotes are the names of the actual POST fields.
?>