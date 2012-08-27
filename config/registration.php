<?php
	# the time interval after which a registration request times out
	define('REGISTRATION_TIMEOUT', '30 MINUTE');
	# The value must follow the rules of the MySQL DATE_ADD() function (`expr` followed by `unit`)
	# (http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-add).

	# a list of URLs to POST data to after a successful registration, separated by space
	define('POST_REGISTRATION_URLS', '');
	# each of these URLs receives the following POST data:
	#   + user name ('user')
	#   + user id ('id')
	#   + mail address ('mail')
	# (The words in brackets are the names of the actual POST parameters.)

	# a Perl Compatible Regular Expression (PCRE) that is used to validate a user name
	define('USER_NAME_REGEX', '/^\w[\w\[\]\-\.\|\$\§\<\>]{2,25}$/i');
	# If a user name does not fit this RegEx, the request to initiate a registration is ans-
	# wered with a '403 - Forbidden' status code.
	#
	# Notes:
	#   + When the maximum length is increased, the database fields must be adjusted accordingly.
	#     This affects the 'DB_TABLE_USERS' database table and the 'DB_TABLE_REGISTRATION' table.
?>